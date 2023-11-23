<?php

declare(strict_types=1);

namespace Nets\Checkout\Storefront\Controller;

use Exception;
use Nets\Checkout\Core\Content\NetsPaymentApi\NetsPaymentEntity;
use Nets\Checkout\Service\ConfigService;
use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Nets\Checkout\Service\Easy\CheckoutService;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends StorefrontController
{
    public SystemConfigService $systemConfigService;

    private EntityRepository $orderRepository;

    private ConfigService $configService;

    private CheckoutService $checkout;

    private EasyApiService $easyApiService;

    private CartService $cartService;

    private PaymentService $paymentService;

    private EntityRepository $netsApiRepository;

    private OrderTransactionStateHandler $transHandler;

    private StateMachineRegistry $stateMachineRegistry;

    private AbstractCartOrderRoute $orderRoute;

    private RequestStack $requestStack;

    private EntityRepository $orderTransactionRepo;

    public function __construct(EntityRepository $orderRepository, CheckoutService $checkout, SystemConfigService $systemConfigService, EasyApiService $easyApiService, ConfigService $configService, CartService $cartService, PaymentService $paymentService, EntityRepository $netsApiRepository, OrderTransactionStateHandler $transHandler, StateMachineRegistry $machineRegistry, AbstractCartOrderRoute $orderRoute, RequestStack $requestStack, EntityRepository $orderTransactionRepo)
    {
        $this->orderRepository      = $orderRepository;
        $this->checkout             = $checkout;
        $this->systemConfigService  = $systemConfigService;
        $this->easyApiService       = $easyApiService;
        $this->configService        = $configService;
        $this->cartService          = $cartService;
        $this->paymentService       = $paymentService;
        $this->netsApiRepository    = $netsApiRepository;
        $this->transHandler         = $transHandler;
        $this->stateMachineRegistry = $machineRegistry;
        $this->orderRoute           = $orderRoute;
        $this->requestStack         = $requestStack;
        $this->orderTransactionRepo = $orderTransactionRepo;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     *
     * @Route("/nets/order/finish", name="nets.finish.order.controller", options={"seo": "false"}, methods={"GET"}, defaults={"_routeScope": {"storefront"}})
     *
     * @throws EasyApiException
     *
     * @return null|RedirectResponse
     */
    public function placeOrder(Context $context, SalesChannelContext $ctx, Request $request, RequestDataBag $data): RedirectResponse
    {
        $cart = $this->cartService->getCart($ctx->getToken(), $ctx);

        try {
            $orderId = $this->orderRoute->order($cart, $ctx, $data)->getOrder()->getId();
        } catch (Exception $e) {
        }

        if (empty($orderId)) {
            $orderId = $request->getSession()->get('orderId');
        }

        $orderEntity    = $this->getOrderEntityById($context, $orderId);
        $transaction    = $orderEntity->getTransactions()->first();
        $salesChannelId = $ctx->getSalesChannel()->getId();
        $secretKey      = $this->configService->getSecretKey($salesChannelId);
        $environment    = $this->configService->getEnvironment($salesChannelId);
        $this->easyApiService->setEnv($environment);
        $this->easyApiService->setAuthorizationKey($secretKey);
        $payment     = $this->easyApiService->getPayment($this->requestStack->getCurrentRequest()->get('paymentId'));
        $checkoutUrl = $payment->getCheckoutUrl();
        $refUpdate   = [
            'reference'   => $orderEntity->getOrderNumber(),
            'checkoutUrl' => $checkoutUrl,
        ];
        $this->easyApiService->updateReference($this->requestStack->getCurrentRequest()->get('paymentId'), json_encode($refUpdate));

        // For inserting amount available respect to charge id
        if ($this->configService->getChargeNow($ctx->getsalesChannel()->getId()) == 'yes' || $payment->getPaymentType() == 'A2A') {
            $this->netsApiRepository->create([
            [
            'order_id'         => $orderId ? $orderId : '',
            'charge_id'        => $payment->getFirstChargeId() ? $payment->getFirstChargeId() : '',
            'operation_type'   => 'capture',
            'operation_amount' => $payment->getChargedAmount() ? $payment->getChargedAmount() / 100 : '',
            'amount_available' => $payment->getChargedAmount() ? $payment->getChargedAmount() / 100 : '',
            ],
            ], $context);

            $this->stateMachineRegistry->transition(new Transition(
                OrderTransactionDefinition::ENTITY_NAME,
                $orderEntity->getTransactions()->first()
                ->getId(),
                StateMachineTransitionActions::ACTION_PAID,
                'stateId'
            ), $context);
        }

        $this->orderTransactionRepo->update([
               [
                   'id'           => $transaction->getId(),
                   'customFields' => [
                       'nets_easy_payment_details' => [
                           'transaction_id' => $this->requestStack->getCurrentRequest()->get('paymentId'),
                           'can_capture'    => true,
                       ],
                   ],
               ],
           ], $context);

        $finishUrl = $this->generateUrl('frontend.checkout.finish.page',
            [
                'orderId' => $orderId,
            ]);

        try {
            $result = $this->paymentService->handlePaymentByOrder($orderId, $data, $ctx, $finishUrl);

            return new RedirectResponse($finishUrl);
        } catch (Exception $e) {
        }
    }

    /**
     * @RouteScope(scopes={"storefront"})
     *
     * @Route("/nets/caheckout/validate", name="nets.test.controller.validate", options={"seo": "false"}, methods={"GET"}, defaults={"_routeScope": {"storefront"}})
     */
    public function validate(SalesChannelContext $ctx)
    {
        try {
            $secretKey = $this->configService->getSecretKey($ctx->getSalesChannel()
                ->getId());
            $environment = $this->configService->getSecretKey($ctx->getSalesChannel()
                ->getId());
            $this->easyApiService->setEnv($environment);
            $this->easyApiService->setAuthorizationKey($secretKey);
            $payment = $this->easyApiService->getPayment($this->requestStack->getCurrentRequest()->get('paymentid'));

            if (empty($payment->getReservedAmount())) {
                return $this->redirectToRoute('frontend.checkout.cart.page');
            }
        } catch (EasyApiException $ex) {
            return $this->redirectToRoute('frontend.checkout.cart.page');
        }
    }

    /**
     * @RouteScope(scopes={"api"})
     *
     * @Route("/api/nets/transaction/charge", name="nets.charge.payment.action", options={"seo": "false"}, methods={"POST"}, defaults={"_routeScope": {"api"}})
     */
    public function chargePayment(Context $context, Request $request): JsonResponse
    {
        $orderId        = $request->get('params')['orderId'];
        $paymentId      = $request->get('params')['paymentId'];
        $amount         = $request->get('params')['amount'];
        $orderEntity    = $this->getOrderEntityById($context, $orderId);
        $salesChannelId = $this->getSalesChannelIdByOrderId($orderId, $context);

        try {
            $this->checkout->chargePayment($orderEntity, $salesChannelId, $context, $paymentId, $amount);
        } catch (EasyApiException $ex) {
            return new JsonResponse([
                'status'  => false,
                'message' => $ex->getMessage(),
                'code'    => $ex->getCode(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (Exception $ex) {
            return new JsonResponse([
                'status'  => false,
                'message' => $ex->getMessage(),
                'code'    => 0,
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'status' => true,
        ]);
    }

    /**
     * @RouteScope(scopes={"api"})
     *
     * @Route("/api/nets/transaction/summary", name="nets.summary.payment.action", options={"seo": "false"}, methods={"POST"}, defaults={"_routeScope": {"api"}})
     *
     * @return JsonResponse
     * @throws EasyApiException
     */
    public function getSummaryAmounts(Context $context, Request $request)
    {
        $orderId        = $request->get('params')['transaction']['orderId'];
        $salesChannelId = $this->getSalesChannelIdByOrderId($orderId, $context);
        $environment    = $this->configService->getEnvironment($salesChannelId);
        $secretKey      = $this->configService->getSecretKey($salesChannelId);
        $this->easyApiService->setEnv($environment);
        $this->easyApiService->setAuthorizationKey($secretKey);
        $orderEntity = $this->getOrderEntityById($context, $orderId);
        $transaction = $orderEntity->getTransactions()->first();
        $paymentId   = $request->get('params')['transaction']['customFields']['nets_easy_payment_details']['transaction_id'];
        $payment     = $this->easyApiService->getPayment($paymentId);

        $orderStatus                 = $orderEntity->getStateMachineState()->getTechnicalName();
        $amountAvailableForCapturing = 0;
        $amountAvailableForRefunding = 0;
        $refundPendingStatus         = false;

        if ($orderStatus == OrderStates::STATE_CANCELLED) {
            $secretKey   = $this->configService->getSecretKey($salesChannelId);
            $environment = $this->configService->getEnvironment($salesChannelId);
            $this->easyApiService->setEnv($environment);
            $this->easyApiService->setAuthorizationKey($secretKey);

            if ($transaction->getStateMachineState()->getTechnicalName() != OrderStates::STATE_CANCELLED) {
                $this->transHandler->cancel($orderEntity->getTransactions()->first()->getId(), $context);
            }

            if ($transaction->getStateMachineState()->getTechnicalName() == OrderStates::STATE_CANCELLED) {
                $payment = $this->easyApiService->getPayment($paymentId);

                if (empty($payment->getCancelledAmount())) {
                    $cancelBody = [
                        'amount' => $payment->getReservedAmount(),
                    ];

                    try {
                        $paymentVoid = $this->easyApiService->voidPayment($paymentId, json_encode($cancelBody));
                    } catch (Exception $e) {
                    }
                }
            }
        } else {
            if ($payment->getChargedAmount() == 0) {
                $amountAvailableForCapturing = $payment->getOrderAmount() / 100;
            } else {
                $amountAvailableForCapturing = ($payment->getReservedAmount() - $payment->getChargedAmount()) / 100;
            }

            if ($payment->getChargedAmount() > 0 && $payment->getRefundedAmount() == 0) {
                $amountAvailableForRefunding = $payment->getChargedAmount() / 100;
            } elseif ($payment->getChargedAmount() - $payment->getRefundedAmount() > 0) {
                $amountAvailableForRefunding = ($payment->getChargedAmount() - $payment->getRefundedAmount()) / 100;
            }

            if ($payment->getChargedAmount() > 0) {
                $response = $payment->getAllCharges();
                foreach ($response as $key) {
                    $criteria = new Criteria();
                    $criteria->addFilter(new EqualsFilter('charge_id', $key->chargeId));
                    $chargeId = $this->netsApiRepository->search($criteria, $context)->first();

                    if (empty($chargeId)) {
                        $this->netsApiRepository->create([
                        [
                        'order_id'         => $orderId ? $orderId : '',
                        'charge_id'        => $key->chargeId,
                        'operation_type'   => 'capture',
                        'operation_amount' => $key->amount / 100,
                        'amount_available' => $key->amount / 100,
                        ],
                        ], $context);
                    }
                }
            }

            if ($payment->getRefundedAmount() == 0) {
                if ($payment->getReservedAmount() == $payment->getChargedAmount()) {
                    if ($transaction->getStateMachineState()->getTechnicalName() == OrderTransactionStates::STATE_PARTIALLY_PAID) {
                        $this->transHandler->reopen($orderEntity->getTransactions()->first()
                           ->getId(), $context);

                        $this->stateMachineRegistry->transition(new Transition(
                            OrderTransactionDefinition::ENTITY_NAME,
                            $orderEntity->getTransactions()->first()
                            ->getId(),
                            StateMachineTransitionActions::ACTION_PAID,
                            'stateId'
                        ), $context);
                    } elseif ($transaction->getStateMachineState()->getTechnicalName() != OrderTransactionStates::STATE_PAID) {
                        $this->transHandler->paid($orderEntity->getTransactions()->first()->getId(), $context);
                    }
                } elseif ($payment->getChargedAmount() < $payment->getReservedAmount() && $payment->getChargedAmount() > 0 && $transaction->getStateMachineState()->getTechnicalName() != OrderTransactionStates::STATE_PARTIALLY_PAID) {
                    $this->transHandler->payPartially($orderEntity->getTransactions()->first()
                            ->getId(), $context);
                }
            }

            if ($payment->getRefundedAmount() > 0) {
                if ($payment->getChargedAmount() == $payment->getRefundedAmount() && $transaction->getStateMachineState()->getTechnicalName() != OrderTransactionStates::STATE_REFUNDED) {
                    $this->transHandler->refund($orderEntity->getTransactions()->first()
                            ->getId(), $context);
                } elseif ($payment->getRefundedAmount() < $payment->getChargedAmount() && $transaction->getStateMachineState()->getTechnicalName() != OrderTransactionStates::STATE_PARTIALLY_REFUNDED) {
                    $this->transHandler->refundPartially($orderEntity->getTransactions()->first()
                            ->getId(), $context);
                }
            }

            // Refund functionality
            $chargeArrWithAmountAvailable = [];
            $chargeIdArr                  = [];
            $chargeIdArr                  = $payment->getAllCharges();
            $refundResult                 = false;
            $remainingAmount              = null;

            if (!empty($chargeIdArr)) {
                foreach ($chargeIdArr as $row) {
                    // select query based on charge to get amount available
                    $criteria = new Criteria();
                    $criteria->addFilter(new EqualsFilter('charge_id', $row->chargeId));
                    /** @var null|NetsPaymentEntity $result */
                    $result = $this->netsApiRepository->search($criteria, $context)->first();

                    if ($result) {
                        $chargeArrWithAmountAvailable[$row->chargeId] = $result->getAvailableAmt();
                        $remainingAmount += $result->getAvailableAmt();
                    }
                }
                array_multisort($chargeArrWithAmountAvailable, SORT_DESC);
            }

            // second block
            $refundsArray = $payment->getAllRefund();

            $amountAvailableForRefunding = ($payment->getChargedAmount() - $payment->getRefundedAmount()) / 100;

            if ($payment->getRefundedAmount() > 0 && $remainingAmount != $amountAvailableForRefunding) {
                foreach ($refundsArray as $ky) {
                    $amountToRefund      = $ky->amount / 100;
                    $refundChargeIdArray = [];
                    foreach ($chargeArrWithAmountAvailable as $key => $value) {
                        $criteria = new Criteria();
                        $criteria->addFilter(new EqualsFilter('charge_id', $key));
                        /** @var null|NetsPaymentEntity $resultCharge */
                        $resultCharge = $this->netsApiRepository->search($criteria, $context)->first();

                        if ($resultCharge->getAvailableAmt() > 0) {
                            if ($amountToRefund <= $value) {
                                $refundChargeIdArray[$key] = $amountToRefund;

                                break;
                            }

                            if ($ky->amount >= $value) {
                                $ky->amount                = $ky->amount - $value;
                                $refundChargeIdArray[$key] = $value;
                            } else {
                                $refundChargeIdArray[$key] = $ky->amount;
                            }

                            if (array_sum($refundChargeIdArray) == $amountToRefund) {
                                break;
                            }
                        }
                    }

                    // third block
                    if ($amountToRefund <= array_sum($refundChargeIdArray)) {
                        $refundResult = true;
                        $count        = 0;
                        foreach ($refundChargeIdArray as $key => $value) {
                            // update table for amount available
                            if ($refundResult) {
                                // get amount available based on charge id
                                $criteria = new Criteria();
                                $criteria->addFilter(new EqualsFilter('charge_id', $key));
                                /** @var null|NetsPaymentEntity $result */
                                $result = $this->netsApiRepository->search($criteria, $context)->first();

                                if ($result) {
                                    $availableAmount = $result->getAvailableAmt() - $value;
                                    $update          = [
                                        'id'               => $result->getId(),
                                        'amount_available' => $availableAmount,
                                    ];
                                    $this->netsApiRepository->update([
                                        $update,
                                    ], $context);
                                }
                            }
                        }
                    }
                }
            }

            if (!empty($refundsArray)) {
                foreach ($refundsArray as $row) {
                    if ($row->state == 'Pending') {
                        $refundPendingStatus = true;
                    }
                }
            }

            return new JsonResponse([
                'amountAvailableForCapturing' => $amountAvailableForCapturing,
                'amountAvailableForRefunding' => ($payment->getChargedAmount() - $payment->getRefundedAmount()) / 100,
                'orderState'                  => $transaction->getStateMachineState()->getTechnicalName(),
                'refundPendingStatus'         => $refundPendingStatus,
                'paymentMethod'               => $payment->getPaymentMethod(),
                'refunded'                    => $payment->getRefundedAmount(),
            ]);
        }
    }

    /**
     * @RouteScope(scopes={"api"})
     *
     * @Route("/api/nets/transaction/refund", name="nets.refund.payment.action", options={"seo": "false"}, methods={"POST"}, defaults={"_routeScope": {"api"}})
     *
     * @throws Exception
     */
    public function refundPayment(Context $context, Request $request): JsonResponse
    {
        $orderId        = $request->get('params')['orderId'];
        $paymentId      = $request->get('params')['paymentId'];
        $amount         = $request->get('params')['amount'];
        $orderEntity    = $this->getOrderEntityById($context, $orderId);
        $salesChannelId = $this->getSalesChannelIdByOrderId($orderId, $context);

        try {
            $this->checkout->refundPayment($orderEntity, $salesChannelId, $context, $paymentId, $amount);
        } catch (EasyApiException $ex) {
            return new JsonResponse([
                'status'  => false,
                'message' => $ex->getMessage(),
                'code'    => $ex->getCode(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (Exception $ex) {
            return new JsonResponse([
                'status'  => false,
                'message' => $ex->getMessage(),
                'code'    => 0,
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'status' => true,
        ]);
    }

    /**
     * @RouteScope(scopes={"storefront"})
     *
     * @Route("/nets/order/cancel", name="nets.cancel.order.controller", options={"seo": "false"}, methods={"GET"}, defaults={"_routeScope": {"storefront"}})
     *
     * @throws EasyApiException
     */
    public function cancelOrder(Context $context, SalesChannelContext $ctx, Request $request, RequestDataBag $data): RedirectResponse
    {
        $session        = $request->getSession();
        $orderId        = $session->get('sw_order_id');
        $orderNo        = $session->get('cancelOrderId');
        $salesChannelId = $this->getSalesChannelIdByOrderId($orderId, $context);
        $orderEntity    = $this->getOrderEntityById($context, $orderId);
        $transaction    = $orderEntity->getTransactions()->first();

        $this->stateMachineRegistry->transition(new Transition(
            OrderDefinition::ENTITY_NAME,
            $orderId,
            StateMachineTransitionActions::ACTION_CANCEL,
            'stateId'
        ), $context);

        $this->transHandler->cancel($orderEntity->getTransactions()->first()->getId(), $context);

        return $this->redirectToRoute('frontend.checkout.cart.page');
    }

    /**
     * @RouteScope(scopes={"api"})
     *
     * @Route("/api/test/verify", name="nets.api.test.controller", defaults={"XmlHttpRequest": true, "_routeScope": {"api"}}, options={"seo": "false"}, methods={"POST"})
     */
    public function check(Context $context, Request $request, RequestDataBag $dataBag): JsonResponse
    {
        $environment  = $dataBag->get('NetsCheckout.config.enviromnent');
        $checkoutType = $dataBag->get('NetsCheckout.config.checkoutType');
        $success      = false;

        if ($environment == 'test') {
            $secretKey = $dataBag->get('NetsCheckout.config.testSecretKey');

            if ($checkoutType == 'embedded') {
                $checkoutKey = $dataBag->get('NetsCheckout.config.testCheckoutKey');
            }
        } else {
            $secretKey = $dataBag->get('NetsCheckout.config.liveSecretKey');

            if ($checkoutType == 'embedded') {
                $checkoutKey = $dataBag->get('NetsCheckout.config.liveCheckoutKey');
            }
        }

        if ($checkoutType == 'hosted') {
            if (empty($secretKey)) {
                return new JsonResponse(['success' => $success]);
            }
            $integrationType = 'HostedPaymentPage';
            $url             = '"returnUrl": "https://localhost","cancelUrl": "https://localhost"';
        } else {
            if (empty($secretKey) or empty($checkoutKey)) {
                return new JsonResponse(['success' => $success]);
            }
            $integrationType = 'EmbeddedCheckout';
            $url             = '"url": "https://localhost"';
        }

        $connection       = Kernel::getConnection();
        $currencyiso_code = $connection->fetchOne(
            'SELECT `iso_code` FROM `currency` WHERE `id` = :currencyId',
            ['currencyId' => Uuid::fromHexToBytes($context->getCurrencyId())]
        );

        $payload = '{
				  "checkout": {
					"integrationType": "' . $integrationType . '",
					"termsUrl": "' . $dataBag->get('NetsCheckout.config.termsUrl') . '",
					"merchantHandlesConsumerData":true,' .
                     $url . '
				  },
				  "order": {
					"items": [
					  {
						"reference": "Test001",
						"name": "Demo product",
						"quantity": 1,
						"unit": "pcs",
						"unitPrice": 1000,
						"grossTotalAmount": 1000,
						"netTotalAmount": 1000
					  }
					],
					"amount": 1000,
					"currency": "' . (empty($currencyiso_code) ? 'EUR' : $currencyiso_code) . '",
					"reference": "Demo Test Order"
				  }
				}';

        $this->easyApiService->setEnv($environment);
        $this->easyApiService->setAuthorizationKey($secretKey);

        $result = $this->easyApiService->createPayment($payload);

        if ($result) {
            $response = json_decode($result, true);

            if (!empty($response['paymentId'])) {
                $success = true;
            }
        }

        return new JsonResponse(['success' => $success]);
    }

    /**
     * @param $orderId
     *
     * @return null|mixed
     */
    private function getOrderEntityById(Context $context, $orderId)
    {
        $criteria = new Criteria([
            $orderId,
        ]);
        $criteria->addAssociation('lineItems.payload')
            ->addAssociation('deliveries.shippingCosts')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('cartPrice.calculatedTaxes')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('currency')
            ->addAssociation('addresses.country')
            ->addAssociation('transactions.stateMachineState');

        return $this->orderRepository->search($criteria, $context)->first();
    }

    /**
     * @throws OrderNotFoundException
     */
    private function getSalesChannelIdByOrderId(string $orderId, Context $context): string
    {
        /** @var null|OrderEntity $order */
        $order = $this->orderRepository->search(new Criteria([
            $orderId,
        ]), $context)->first();

        if ($order === null) {
            throw new OrderNotFoundException($orderId);
        }

        return $order->getSalesChannelId();
    }
}
