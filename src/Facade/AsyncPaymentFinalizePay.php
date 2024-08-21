<?php

declare(strict_types=1);

namespace Nets\Checkout\Facade;

use Nets\Checkout\Service\Easy\Api\Payment;
use Nets\Checkout\Service\Easy\ConfigService;
use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiExceptionHandler;
use Nets\Checkout\Service\Easy\CheckoutService;
use Nets\Checkout\Service\Easy\LanguageProvider;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

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

    public function __construct(
        CheckoutService $checkout,
        EasyApiExceptionHandler $easyApiExceptionHandler,
        OrderTransactionStateHandler $transactionStateHandler,
        EasyApiService $easyApiService,
        EntityRepository $orderTransactionRepo,
        ConfigService $configService,
        EntityRepository $orderRepository,
        EntityRepository $netsApiRepository,
        LanguageProvider $languageProvider
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
    }

    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        $orderTransaction = $transaction->getOrderTransaction();
        $transactionId = $orderTransaction->getId();
        $context = $salesChannelContext->getContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $netsTransactionId = $this->getNetsPaymentId($transaction);

        try {
            $payment = $this->easyApiService->getPayment($netsTransactionId, $salesChannelId);
            $orderId = $transaction->getOrder()->getId();
            $chargeNow = $this->configService->getChargeNow($salesChannelId);

            if (!$this->isSameTotalAmount($orderTransaction, $payment)) {
                throw new AsyncPaymentFinalizeException($transactionId, 'Total amount mismatch');
            }

            $this->orderRepository->update(
                [
                    [
                        'id' => $orderId,
                        'customFields' => [
                            'paymentId' => $netsTransactionId,
                        ],
                    ],
                ],
                $context
            );

            if (empty($payment->getReservedAmount()) && empty($payment->getChargedAmount())) {
                throw new AsyncPaymentFinalizeException($transactionId, 'Customer canceled the payment on the Easy payment page');
            }

            $this->transactionStateHandler->authorize(
                $transactionId,
                $context
            );

            if ($chargeNow == 'yes') {
                $this->transactionStateHandler->paid(
                    $transactionId,
                    $context
                );
            }

            // For inserting amount available respect to charge id
            if ($this->configService->getChargeNow($salesChannelId) == 'yes' || $payment->getPaymentType() == 'A2A') {
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
     * @throws AsyncPaymentProcessException|\Exception
     */
    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): string
    {
        $checkoutType = $this->configService->getCheckoutType($salesChannelContext->getSalesChannelId());
        $context = $salesChannelContext->getContext();
        $transactionId = $transaction->getOrderTransaction()->getId();

        if (CheckoutService::CHECKOUT_TYPE_EMBEDDED === $checkoutType) {
            $paymentId = $dataBag->getAlnum('paymentId') ?: $dataBag->getAlnum('paymentid');

            if ($paymentId === '') {
                throw new AsyncPaymentProcessException($transactionId, 'Missing payment id');
            }

            if ($this->getNetsPaymentId($transaction) !== $paymentId) {
                throw new AsyncPaymentProcessException($transactionId, 'Mismatched transaction');
            }

            // EmbeddedCheckoutController::handle creates order, and commits transaction
            return $transaction->getReturnUrl();
        }

        try {
            $result = $this->checkout->createPayment(
                $salesChannelContext,
                CheckoutService::CHECKOUT_TYPE_HOSTED,
                $transaction
            );

            $payment = json_decode($result, true);
            $this->orderTransactionRepo->update(
                [
                    [
                        'id' => $transactionId,
                        'customFields' => [
                            'nets_easy_payment_details' => [
                                'transaction_id' => $payment['paymentId'],
                            ]
                        ]
                    ]
                ],
                $context
            );
        } catch (EasyApiException $ex) {
            $this->easyApiExceptionHandler->handle($ex);
            throw new AsyncPaymentProcessException($transactionId, $ex->getMessage());
        }

        return $this->createUrl($payment['hostedPaymentPageUrl'], $context);
    }

    private function createUrl(string $url, Context $context): string
    {
        return sprintf(
            "%s&language=%s",
            $url,
            $this->languageProvider->getLanguage($context)
        );
    }

    private function getNetsPaymentId(AsyncPaymentTransactionStruct $transaction): string
    {
        return $transaction
            ->getOrderTransaction()
            ->getCustomFields()['nets_easy_payment_details']['transaction_id'];
    }

    private function isSameTotalAmount(OrderTransactionEntity $orderTransactionEntity, Payment $payment): bool
    {
        return $orderTransactionEntity->getAmount()->getTotalPrice() === (float)$payment->getOrderAmount() / 100;
    }
}
