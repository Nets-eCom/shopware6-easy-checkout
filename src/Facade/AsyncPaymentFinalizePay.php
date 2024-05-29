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
use Shopware\Core\Checkout\Payment\PaymentException;
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
        LanguageProvider $languageProvider,
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
        $transactionId = $transaction->getOrderTransaction()->getId();
        $context = $salesChannelContext->getContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $netsTransactionId = $transaction
            ->getOrderTransaction()
            ->getCustomFieldsValue('nets_easy_payment_details')['transaction_id'];

        try {
            $payment = $this->easyApiService->getPayment($netsTransactionId, $salesChannelId);
            $orderId = $transaction->getOrder()->getId();
            $chargeNow = $this->configService->getChargeNow($salesChannelId);

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
                throw PaymentException::asyncFinalizeInterrupted($transactionId, 'Customer canceled the payment on the Easy payment page');
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
            throw PaymentException::asyncFinalizeInterrupted($transactionId, 'Exception during transaction completion');
        }
    }

    /**
     * @throws PaymentException|\Exception
     */
    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): string
    {
        $checkoutType = $this->configService->getCheckoutType($salesChannelContext->getSalesChannelId());
        $context = $salesChannelContext->getContext();
        $transactionId = $transaction->getOrderTransaction()->getId();

        if (CheckoutService::CHECKOUT_TYPE_EMBEDDED === $checkoutType) {
            $paymentId = $dataBag->getString('paymentId') ?: $dataBag->getString('paymentid');

            if ($paymentId === '') {
                throw PaymentException::asyncProcessInterrupted($transactionId, 'Missing payment id');
            }

            $netsTransactionId = $transaction
                ->getOrderTransaction()
                ->getCustomFieldsValue('nets_easy_payment_details')['transaction_id'];

            if ($netsTransactionId !== $paymentId) {
                throw PaymentException::asyncProcessInterrupted($transactionId, 'Mismatched transaction');
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
            throw PaymentException::asyncProcessInterrupted($transactionId, $ex->getMessage());
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
}
