<?php

declare(strict_types=1);

namespace Nets\Checkout\Controller\API;

use Nets\Checkout\Core\Content\NetsPaymentApi\NetsPaymentEntity;
use Nets\Checkout\Service\DataReader\OrderDataReader;
use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Nets\Checkout\Service\Easy\CheckoutService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Kernel;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(defaults={"_routeScope"={"api"}})
 */
class APIController extends StorefrontController
{
    private CheckoutService $checkout;
    private EasyApiService $easyApiService;
    private EntityRepository $netsApiRepository;
    private OrderTransactionStateHandler $transHandler;
    private StateMachineRegistry $stateMachineRegistry;
    private OrderDataReader $orderDataReader;

    public function __construct(
        CheckoutService $checkout,
        EasyApiService $easyApiService,
        EntityRepository $netsApiRepository,
        OrderTransactionStateHandler $transHandler,
        StateMachineRegistry $machineRegistry,
        OrderDataReader $orderDataReader
    ) {
        $this->checkout             = $checkout;
        $this->easyApiService       = $easyApiService;
        $this->netsApiRepository    = $netsApiRepository;
        $this->transHandler         = $transHandler;
        $this->stateMachineRegistry = $machineRegistry;
        $this->orderDataReader      = $orderDataReader;
    }

    /**
     * @Route("/api/nets/transaction/charge", name="nets.charge.payment.action", options={"seo": "false"}, methods={"POST"})
     */
    public function chargePayment(Context $context, Request $request): JsonResponse
    {
        $orderId = $request->get('params')['orderId'];
        $paymentId = $request->get('params')['paymentId'];
        $amount = $request->get('params')['amount'];
        $orderEntity = $this->orderDataReader->getOrderEntityById($context, $orderId);

        try {
            $this->checkout->chargePayment($orderEntity, $context, $paymentId, $amount);
        } catch (EasyApiException $ex) {
            return new JsonResponse([
                'status'  => false,
                'message' => $ex->getMessage(),
                'code'    => $ex->getCode(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $ex) {
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
     * @Route("/api/nets/transaction/summary", name="nets.summary.payment.action", options={"seo": "false"}, methods={"POST"})
     *
     * @throws EasyApiException
     */
    public function getSummaryAmounts(Context $context, Request $request): JsonResponse
    {
        $orderId     = $request->get('params')['transaction']['orderId'];
        $orderEntity = $this->orderDataReader->getOrderEntityById($context, $orderId);
        $transaction = $orderEntity->getTransactions()->first();
        $paymentId   = $request->get('params')['transaction']['customFields']['nets_easy_payment_details']['transaction_id'];
        $payment     = $this->easyApiService->getPayment($paymentId);

        $orderStatus                 = $orderEntity->getStateMachineState()->getTechnicalName();
        $refundPendingStatus         = false;

        if ($orderStatus == OrderStates::STATE_CANCELLED) {

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
                        $this->easyApiService->voidPayment($paymentId, json_encode($cancelBody));
                    } catch (\Exception $e) {
                    }
                }
            }
            return new JsonResponse();
        } else {
            if ($payment->getChargedAmount() == 0) {
                $amountAvailableForCapturing = $payment->getOrderAmount() / 100;
            } else {
                $amountAvailableForCapturing = ($payment->getReservedAmount() - $payment->getChargedAmount()) / 100;
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
                    } elseif ($transaction->getStateMachineState()->getTechnicalName() !== OrderTransactionStates::STATE_CANCELLED) {
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
            $chargeIdArr                  = $payment->getAllCharges();
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
                        foreach ($refundChargeIdArray as $key => $value) {
                            // update table for amount available
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
     * @Route("/api/nets/transaction/refund", name="nets.refund.payment.action", options={"seo": "false"}, methods={"POST"})
     *
     * @throws \Exception
     */
    public function refundPayment(Context $context, Request $request): JsonResponse
    {
        $orderId        = $request->get('params')['orderId'];
        $paymentId      = $request->get('params')['paymentId'];
        $amount         = $request->get('params')['amount'];
        $orderEntity    = $this->orderDataReader->getOrderEntityById($context, $orderId);

        try {
            $this->checkout->refundPayment($orderEntity, $context, $paymentId, $amount);
        } catch (EasyApiException $ex) {
            return new JsonResponse([
                'status'  => false,
                'message' => $ex->getMessage(),
                'code'    => $ex->getCode(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $ex) {
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
     * @Route("/api/nets/test/verify", name="nets.api.test.controller", options={"seo": "false"}, methods={"POST"})
     * @throws EasyApiException|\Exception
     */
    public function check(Context $context, Request $request, RequestDataBag $dataBag): JsonResponse
    {
        $environment = $dataBag->get('NetsCheckout.config.enviromnent');
        $checkoutType = $dataBag->get('NetsCheckout.config.checkoutType');

        $secretKey = $environment === EasyApiService::ENV_LIVE
            ? $dataBag->get('NetsCheckout.config.liveSecretKey')
            : $dataBag->get('NetsCheckout.config.testSecretKey');

        if (empty($secretKey)
            || !in_array($checkoutType, [CheckoutService::CHECKOUT_TYPE_HOSTED, CheckoutService::CHECKOUT_TYPE_EMBEDDED])
        ) {
            return new JsonResponse(['success' => false]);
        }

        $integrationType = 'HostedPaymentPage';
        $urls = '"returnUrl": "https://localhost","cancelUrl": "https://localhost"';
        if ($checkoutType === CheckoutService::CHECKOUT_TYPE_EMBEDDED) {
            $checkoutKey = $environment === EasyApiService::ENV_LIVE
                ? $dataBag->get('NetsCheckout.config.liveCheckoutKey')
                : $dataBag->get('NetsCheckout.config.testCheckoutKey');

            if (empty($checkoutKey)) {
                return new JsonResponse(['success' => false]);
            }

            $integrationType = 'EmbeddedCheckout';
            $urls = '"url": "https://localhost"';
        }

        $connection       = Kernel::getConnection();
        $currency_iso_code = $connection->fetchOne(
            'SELECT `iso_code` FROM `currency` WHERE `id` = :currencyId',
            ['currencyId' => Uuid::fromHexToBytes($context->getCurrencyId())]
        );

        $payload = '{
                  "checkout": {
                    "integrationType": "' . $integrationType . '",
                    "termsUrl": "' . $dataBag->get('NetsCheckout.config.termsUrl') . '",
                    "merchantHandlesConsumerData":true,' .
                    $urls . '
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
                    "currency": "' . (empty($currency_iso_code) ? 'EUR' : $currency_iso_code) . '",
                    "reference": "Demo Test Order"
                  }
                }';

        $success = $this->easyApiService->verifyConnection($environment, $secretKey, $payload);

        return new JsonResponse(['success' => $success]);
    }
}
