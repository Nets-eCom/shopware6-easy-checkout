<?php

declare(strict_types=1);

namespace NexiNets\Order;

use NexiNets\Administration\Model\Item;
use NexiNets\Administration\Model\RefundData;
use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Core\Content\NetsCheckout\Event\RefundChargeSend;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\Fetcher\PaymentFetcherInterface;
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
     * @throws PaymentApiException
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
            $api->refundCharge(
                $payment->getCharges()[0]->getChargeId(),
                $refundRequest
            );

            $this->eventDispatcher->dispatch(
                new RefundChargeSend($order, $transaction, $refundRequest->getAmount())
            );
        }
    }

    /**
     * @throws PaymentApiException
     */
    public function partialRefund(OrderEntity $order, RefundData $refundData): void
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
            $status = $payment->getStatus();

            if (
                !in_array(
                    $status,
                    [
                        PaymentStatusEnum::PARTIALLY_REFUNDED,
                        PaymentStatusEnum::PARTIALLY_CHARGED,
                        PaymentStatusEnum::CHARGED
                    ],
                    true
                )) {
                $this->logger->info('Payment in incorrect status for partial refund', [
                    'paymentId' => $paymentId,
                    'status' => $status->value,
                ]);
                continue;
            }

            $paymentApi = $this->createPaymentApi($order->getSalesChannelId());

            foreach ($refundData->chargeItems() as $chargeItem) {
                $partialRefund = $this->refundRequest->buildPartialRefund(
                    $transaction,
                    $chargeItem
                );

                $this->logger->info('Partial refund request', [
                    'paymentId' => $paymentId,
                    'refund' => $partialRefund,
                ]);

                try {
                    $response = $paymentApi->refundCharge($chargeItem->getChargeId(), $partialRefund);

                    $this->logger->info('Partial refund success', [
                        'paymentId' => $paymentId,
                        'refundId' => $response->getRefundId(),
                    ]);
                } catch (PaymentApiException $e) {
                    $this->logger->error('Partial refund failed', [
                        'paymentId' => $paymentId,
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }
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
}
