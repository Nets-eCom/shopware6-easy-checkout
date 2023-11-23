<?php

declare(strict_types=1);

namespace Nets\Checkout\Facade;

use Nets\Checkout\Service\ConfigService;
use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiExceptionHandler;
use Nets\Checkout\Service\Easy\CheckoutService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AsyncPaymentFinalizePay
{
    private SystemConfigService $systemConfigService;

    private CheckoutService $checkout;

    private EasyApiExceptionHandler $easyApiExceptionHandler;

    private OrderTransactionStateHandler $transactionStateHandler;

    private EasyApiService $easyApiService;

    private EntityRepository $orderTransactionRepo;

    private ConfigService $configService;

    private EntityRepository $orderRepository;

    private Router $router;

    private EntityRepository $netsApiRepository;

    private EntityRepository $languageRepo;

    private RequestStack $requestStack;

    public function __construct(
        CheckoutService $checkout,
        SystemConfigService $systemConfigService,
        EasyApiExceptionHandler $easyApiExceptionHandler,
        OrderTransactionStateHandler $transactionStateHandler,
        EasyApiService $easyApiService,
        EntityRepository $orderTransactionRepo,
        ConfigService $configService,
        EntityRepository $orderRepository,
        Router $router,
        EntityRepository $netsApiRepository,
        EntityRepository $languageRepo,
        RequestStack $requestStack
    ) {
        $this->systemConfigService     = $systemConfigService;
        $this->checkout                = $checkout;
        $this->easyApiExceptionHandler = $easyApiExceptionHandler;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->easyApiService          = $easyApiService;
        $this->orderTransactionRepo    = $orderTransactionRepo;
        $this->configService           = $configService;
        $this->orderRepository         = $orderRepository;
        $this->router                  = $router;
        $this->netsApiRepository       = $netsApiRepository;
        $this->languageRepo            = $languageRepo;
        $this->requestStack            = $requestStack;
    }

    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        $transactionId = $transaction->getOrderTransaction()->getId();

        $salesChannelContextId = $salesChannelContext->getSalesChannel()->getId();
        $environment           = $this->configService->getEnvironment($salesChannelContextId);
        $secretKey             = $this->configService->getSecretKey($salesChannelContextId);

        try {
            $this->easyApiService->setEnv($environment);
            $this->easyApiService->setAuthorizationKey($secretKey);
            $paymentId = $this->extractPaymentId();

            // it is incorrect check for captured amount
            $payment       = $this->easyApiService->getPayment($paymentId);
            $transactionId = $transaction->getOrderTransaction()->getId();
            $orderId       = $transaction->getOrder()->getId();
            $context       = $salesChannelContext->getContext();
            $chargeNow     = $this->configService->getChargeNow($salesChannelContextId);

            if ($chargeNow == 'yes') {
                $this->transactionStateHandler->paid($transaction->getOrderTransaction()
                    ->getId(), $context);
            }

            $this->orderRepository->update([
                [
                    'id'           => $orderId,
                    'customFields' => [
                        'paymentId' => $paymentId,
                    ],
                ],
            ], $context);

            if (empty($payment->getReservedAmount()) && empty($payment->getChargedAmount())) {
                throw new CustomerCanceledAsyncPaymentException($transactionId, 'Customer canceled the payment on the Easy payment page');
            }

            $this->orderTransactionRepo->update([
                [
                    'id'           => $transactionId,
                    'customFields' => [
                        'nets_easy_payment_details' => [
                            'transaction_id' => $paymentId,
                            'can_capture'    => true,
                        ],
                    ],
                ],
            ], $context);

            // For inserting amount available respect to charge id
            if ($this->configService->getChargeNow($salesChannelContextId) == 'yes' || $payment->getPaymentType() == 'A2A') {
                $this->netsApiRepository->create([
                    [
                        'order_id'         => $orderId ? $orderId : '',
                        'charge_id'        => $payment->getFirstChargeId() ? $payment->getFirstChargeId() : '',
                        'operation_type'   => 'capture',
                        'operation_amount' => $payment->getChargedAmount() ? $payment->getChargedAmount() / 100 : '',
                        'amount_available' => $payment->getChargedAmount() ? $payment->getChargedAmount() / 100 : '',
                    ],
                ], $context);
            }
        } catch (EasyApiException $ex) {
            throw new CustomerCanceledAsyncPaymentException($transactionId, 'Exception during transaction completion');
        }
    }

    /**
     * @return RedirectResponse
     * @throws \Exception
     */
    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext)
    {
        $checkoutType = $this->configService->getCheckoutType($salesChannelContext->getSalesChannel()
            ->getId());

        if ($this->checkout::CHECKOUT_TYPE_EMBEDDED == $checkoutType) {
            $paymentId   = $this->extractPaymentId();
            $redirectUrl = $transaction->getReturnUrl() . '&paymentId=' . $paymentId;

            return new RedirectResponse($redirectUrl);
        }

        try {
            $result              = $this->checkout->createPayment($salesChannelContext, $this->checkout::CHECKOUT_TYPE_HOSTED, $transaction);
            $PaymentCreateResult = json_decode($result, true);
            $this->requestStack->getCurrentRequest()->getSession()->set('nets_paymentId', $PaymentCreateResult['paymentId']);
        } catch (EasyApiException $ex) {
            $this->easyApiExceptionHandler->handle($ex);

            return new RedirectResponse($this->router->generate('frontend.checkout.cart.page'));
        }

        $langShort = $this->configService->getLang($salesChannelContext->getContext(), $this->languageRepo);

        switch ($langShort) {
            case 'de':
                $language = 'de-DE';

                break;
            case 'da':
                $language = 'da-DK';

                break;
            case 'sv':
                $language = 'sv-SE';

                break;
            case 'nb':
                $language = 'nb-NO';

                break;
            default:
                $language = 'en-GB';
        }

        return $PaymentCreateResult['hostedPaymentPageUrl'] . '&language=' . $language;
    }

    private function extractPaymentId()
    {
        if (!empty($this->requestStack->getCurrentRequest()->get('paymentId'))) {
            return $this->requestStack->getCurrentRequest()->get('paymentId');
        }

        if (!empty($this->requestStack->getCurrentRequest()->get('paymentid'))) {
            return $this->requestStack->getCurrentRequest()->get('paymentid');
        }
    }
}
