<?php

declare(strict_types=1);

namespace NexiNets\Order;

use NexiNets\Administration\Model\ChargeData;
use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\Fetcher\PaymentFetcherInterface;
use NexiNets\RequestBuilder\ChargeRequest;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class OrderCharge
{
    public function __construct(
        private readonly PaymentFetcherInterface $fetcher,
        private readonly PaymentApiFactory $apiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly ChargeRequest $chargeRequest,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws PaymentApiException
     * @throws \LogicException
     */
    public function fullCharge(OrderEntity $order): void
    {
        if ($this->configurationProvider->isAutoCharge($order->getSalesChannelId())) {
            return;
        }

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

            $paymentApi = $this->createPaymentApi($order->getSalesChannelId());
            $payment = $this->fetcher->fetchPayment($order->getSalesChannelId(), $paymentId);

            if ($payment->getStatus() !== PaymentStatusEnum::RESERVED) {
                $this->logger->info('Payment in incorrect status for full charge', [
                    'paymentId' => $paymentId,
                    'status' => $payment->getStatus()->value,
                ]);
                continue;
            }

            $payload = $this->chargeRequest->buildFullCharge($transaction);
            $this->logger->info('Full charge request', [
                'paymentId' => $paymentId,
                'payload' => $payload,
            ]);

            try {
                $response = $paymentApi->charge($paymentId, $payload);

                $this->logger->info('Full charge success', [
                    'paymentId' => $paymentId,
                    'chargeId' => $response->getChargeId(),
                ]);
            } catch (PaymentApiException $e) {
                $this->logger->error('Full charge failed', [
                    'paymentId' => $paymentId,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }
    }

    public function partialCharge(OrderEntity $order, ChargeData $chargeData): void
    {
        if ($this->configurationProvider->isAutoCharge($order->getSalesChannelId())) {
            return;
        }

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

            // need to assign order because there is no addAssociation from OrderTransactionEntity to OrderEntity in query
            // only association is from OrderEntity to OrderTransactionEntity
            $transaction->setOrder($order);

            $paymentApi = $this->createPaymentApi($order->getSalesChannelId());
            $payment = $this->fetcher->fetchPayment($order->getSalesChannelId(), $paymentId);

            if (!\in_array($payment->getStatus(), [PaymentStatusEnum::RESERVED, PaymentStatusEnum::PARTIALLY_CHARGED], true)) {
                $this->logger->info('Payment in incorrect status for partial charge', [
                    'paymentId' => $paymentId,
                    'status' => $payment->getStatus()->value,
                ]);
                continue;
            }

            $payload = $this->chargeRequest->buildPartialCharge($transaction, $chargeData);
            $this->logger->info('Partial charge request', [
                'paymentId' => $paymentId,
                'payload' => $payload,
            ]);

            try {
                $response = $paymentApi->charge($paymentId, $payload);

                $this->logger->info('Partial charge success', [
                    'paymentId' => $paymentId,
                    'chargeId' => $response->getChargeId(),
                ]);
            } catch (PaymentApiException $e) {
                $this->logger->error('Partial charge failed', [
                    'paymentId' => $paymentId,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
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
