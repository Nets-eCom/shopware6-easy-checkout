<?php

declare(strict_types=1);

namespace Nets\Checkout\Facade;

use Nets\Checkout\Service\Easy\ConfigService;
use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiExceptionHandler;
use Nets\Checkout\Service\Easy\CheckoutService;
use Nets\Checkout\Service\Easy\LanguageProvider;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AsyncPaymentFinalizePay
{
    private CheckoutService $checkout;

    private EasyApiExceptionHandler $easyApiExceptionHandler;

    private OrderTransactionStateHandler $transactionStateHandler;

    private EasyApiService $easyApiService;

    private EntityRepository $orderTransactionRepo;

    private ConfigService $configService;

    private EntityRepository $orderRepository;

    private EntityRepository $netsApiRepository;

    private LanguageProvider $languageProvider;

    private RequestStack $requestStack;

    public function __construct(
        CheckoutService              $checkout,
        EasyApiExceptionHandler      $easyApiExceptionHandler,
        OrderTransactionStateHandler $transactionStateHandler,
        EasyApiService               $easyApiService,
        EntityRepository             $orderTransactionRepo,
        ConfigService                $configService,
        EntityRepository             $orderRepository,
        EntityRepository             $netsApiRepository,
        LanguageProvider             $languageProvider,
        RequestStack                 $requestStack
    ) {
        $this->checkout = $checkout;
        $this->easyApiExceptionHandler = $easyApiExceptionHandler;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->easyApiService = $easyApiService;
        $this->orderTransactionRepo = $orderTransactionRepo;
        $this->configService = $configService;
        $this->orderRepository = $orderRepository;
        $this->netsApiRepository = $netsApiRepository;
        $this->languageProvider = $languageProvider;
        $this->requestStack = $requestStack;
    }

    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        $transactionId = $transaction->getOrderTransaction()->getId();

        try {
            $paymentId = $this->extractPaymentId();

            // it is incorrect check for captured amount
            $payment = $this->easyApiService->getPayment($paymentId);
            $transactionId = $transaction->getOrderTransaction()->getId();
            $orderId = $transaction->getOrder()->getId();
            $context = $salesChannelContext->getContext();
            $chargeNow = $this->configService->getChargeNow();

            if (empty($payment->getReservedAmount()) && empty($payment->getChargedAmount())) {
                throw new AsyncPaymentFinalizeException($transactionId, 'Customer canceled the payment on the Easy payment page');
            }

            $this->transactionStateHandler->authorize(
                $transaction->getOrderTransaction()->getId(),
                $context
            );

            if ($chargeNow == 'yes') {
                $this->transactionStateHandler->paid(
                    $transaction->getOrderTransaction()->getId(),
                    $context
                );
            }

            $this->orderRepository->update([
                [
                    'id' => $orderId,
                    'customFields' => [
                        'paymentId' => $paymentId,
                    ],
                ],
            ], $context);


            $this->orderTransactionRepo->update([
                [
                    'id' => $transactionId,
                    'customFields' => [
                        'nets_easy_payment_details' => [
                            'transaction_id' => $paymentId,
                            'can_capture' => true,
                        ],
                    ],
                ],
            ], $context);

            // For inserting amount available respect to charge id
            if ($this->configService->getChargeNow() == 'yes' || $payment->getPaymentType() == 'A2A') {
                $this->netsApiRepository->create([
                    [
                        'order_id' => $orderId ? $orderId : '',
                        'charge_id' => $payment->getFirstChargeId() ? $payment->getFirstChargeId() : '',
                        'operation_type' => 'capture',
                        'operation_amount' => $payment->getChargedAmount() ? $payment->getChargedAmount() / 100 : '',
                        'amount_available' => $payment->getChargedAmount() ? $payment->getChargedAmount() / 100 : '',
                    ],
                ], $context);
            }
        } catch (EasyApiException $ex) {
            $this->easyApiExceptionHandler->handle($ex);

            throw new AsyncPaymentFinalizeException($transactionId, 'Exception during transaction completion');
        }
    }

    /**
     * @throws AsyncPaymentProcessException
     */
    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): string
    {
        $checkoutType = $this->configService->getCheckoutType();

        if (CheckoutService::CHECKOUT_TYPE_EMBEDDED === $checkoutType) {
            $paymentId = $this->extractPaymentId();

            // for embeded customer already paid in CheckoutConfirmPageSubscriber and js code
            // redirect user to finalize
            return $transaction->getReturnUrl() . '&paymentId=' . $paymentId;
        }

        try {
            $result = $this->checkout->createPayment($salesChannelContext, CheckoutService::CHECKOUT_TYPE_HOSTED, $transaction);
            $PaymentCreateResult = json_decode($result, true);
            $this->requestStack->getCurrentRequest()->getSession()->set('nets_paymentId', $PaymentCreateResult['paymentId']);
        } catch (EasyApiException $ex) {
            $this->easyApiExceptionHandler->handle($ex);

            throw new AsyncPaymentProcessException($transaction->getOrderTransaction()->getId(), $ex->getMessage());
        }

        $language = $this->languageProvider->getLanguage($salesChannelContext->getContext());

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
