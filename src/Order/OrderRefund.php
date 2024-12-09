<?php

declare(strict_types=1);

namespace NexiNets\Order;

use NexiNets\Administration\Model\RefundData;
use NexiNets\CheckoutApi\Api\ErrorCodeEnum;
use NexiNets\CheckoutApi\Api\Exception\InternalErrorPaymentApiException;
use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Core\Content\NetsCheckout\Event\RefundChargeSend;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\Fetcher\PaymentFetcherInterface;
use NexiNets\Order\Exception\OrderChargeRefundExceeded;
use NexiNets\Order\Exception\OrderRefundException;
use NexiNets\RequestBuilder\RefundRequest;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class OrderRefund
{
    public function __construct(
        private readonly PaymentFetcherInterface $fetcher,
        private readonly PaymentApiFactory $apiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly RefundRequest $refundRequest,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws OrderChargeRefundExceeded
     * @throws OrderRefundException
     */
    public function fullRefund(OrderEntity $order): void
    {
        $transactions = $order->getTransactions();

        if (!$transactions instanceof OrderTransactionCollection) {
            throw new \LogicException('No order transactions found');
        }

        /** @var OrderTransactionEntity $transaction */
        foreach ($transactions as $transaction) {
            $paymentId = $transaction->getCustomFieldsValue(
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID
            );

            if ($paymentId === null) {
                continue;
            }

            $payment = $this->fetcher->fetchPayment($order->getSalesChannelId(), $paymentId);

            if ($payment->getStatus() !== PaymentStatusEnum::CHARGED) {
                continue;
            }

            $api = $this->createPaymentApi($order->getSalesChannelId());
            $refundRequest = $this->refundRequest->build($transaction);
            $chargeId = $payment->getCharges()[0]->getChargeId();

            try {
                $api->refundCharge(
                    $chargeId,
                    $refundRequest
                );
            } catch (PaymentApiException|InternalErrorPaymentApiException $e) {
                $this->logFailure([
                    'paymentId' => $paymentId,
                    'error' => $e->getMessage(),
                ]);

                $this->createCorrespondingOrderRefundException($e, $chargeId);
            }

            $this->eventDispatcher->dispatch(
                new RefundChargeSend($order, $transaction)
            );
        }
    }

    /**
     * @throws OrderChargeRefundExceeded
     * @throws OrderRefundException
     */
    public function partialRefund(OrderEntity $order, RefundData $refundData): void
    {
        $transactions = $order->getTransactions();

        if (!$transactions instanceof OrderTransactionCollection) {
            throw new \LogicException('No order transactions found');
        }

        $paymentApi = $this->createPaymentApi($order->getSalesChannelId());

        /** @var OrderTransactionEntity $transaction */
        foreach ($transactions as $transaction) {
            $paymentId = $transaction->getCustomFieldsValue(
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID
            );

            if ($paymentId === null) {
                continue;
            }

            $payment = $this->fetcher->fetchPayment($order->getSalesChannelId(), $paymentId);
            $status = $payment->getStatus();

            if (
                !\in_array(
                    $status,
                    [
                        PaymentStatusEnum::PARTIALLY_REFUNDED,
                        PaymentStatusEnum::PARTIALLY_CHARGED,
                        PaymentStatusEnum::CHARGED,
                    ],
                    true
                )) {
                $this->logger->info('Payment in incorrect status for partial refund', [
                    'paymentId' => $paymentId,
                    'status' => $status->value,
                ]);
                continue;
            }

            foreach ($refundData->getCharges() as $chargeId => $items) {
                $partialRefund = $this->refundRequest->buildPartialRefund(
                    $transaction,
                    $items
                );

                $this->logger->info('Partial refund request', [
                    'paymentId' => $paymentId,
                    'refund' => $partialRefund,
                ]);

                try {
                    $response = $paymentApi->refundCharge($chargeId, $partialRefund);
                } catch (InternalErrorPaymentApiException|PaymentApiException $e) {
                    $this->logFailure([
                        'paymentId' => $paymentId,
                        'error' => $e->getMessage(),
                    ]);

                    throw $this->createCorrespondingOrderRefundException($e, $chargeId);
                }

                $this->logger->info('Partial refund success', [
                    'paymentId' => $paymentId,
                    'refundId' => $response->getRefundId(),
                ]);

                $this->eventDispatcher->dispatch(
                    new RefundChargeSend($order, $transaction)
                );
            }
        }
    }

    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        return $this->apiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId)
        );
    }

    /**
     * @param array<string, string> $parameters
     */
    private function logFailure(array $parameters): void
    {
        $this->logger->error('Partial refund failed', $parameters);
    }

    private function createCorrespondingOrderRefundException(
        PaymentApiException $exception,
        string $chargeId
    ): OrderRefundException {
        if (!$exception instanceof InternalErrorPaymentApiException) {
            return new OrderRefundException($chargeId, previous: $exception);
        }

        return match ($exception->getInternalCode()) {
            ErrorCodeEnum::InvalidRefundAmount => new OrderChargeRefundExceeded($chargeId, previous: $exception),
            default => new OrderRefundException($chargeId, previous: $exception)
        };
    }
}
