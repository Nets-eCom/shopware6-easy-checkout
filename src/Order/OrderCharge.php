<?php

declare(strict_types=1);

namespace NexiNets\Order;

use NexiNets\Administration\Model\ChargeData;
use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\Fetcher\PaymentFetcherInterface;
use NexiNets\Order\Exception\OrderChargeException;
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
     * @throws OrderChargeException
     */
    public function fullCharge(OrderEntity $order): void
    {
        $salesChannelId = $order->getSalesChannelId();
        if ($this->configurationProvider->isAutoCharge($salesChannelId)) {
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

            $payment = $this->fetcher->fetchPayment($salesChannelId, $paymentId);

            $paymentStatus = $payment->getStatus();
            if ($paymentStatus !== PaymentStatusEnum::RESERVED) {
                $this->logger->info('Payment in incorrect status for full charge', [
                    'paymentId' => $paymentId,
                    'status' => $paymentStatus->value,
                ]);
                continue;
            }

            $payload = $this->chargeRequest->buildFullCharge($transaction);
            $this->logger->info('Full charge request', [
                'paymentId' => $paymentId,
                'payload' => $payload,
            ]);

            try {
                $paymentApi = $this->createPaymentApi($salesChannelId);
                $response = $paymentApi->charge($paymentId, $payload);
            } catch (PaymentApiException $e) {
                $this->logger->error('Full charge failed', [
                    'paymentId' => $paymentId,
                    'error' => $e->getMessage(),
                ]);

                throw new OrderChargeException($paymentId, previous: $e);
            }

            $this->logger->info('Full charge success', [
                'paymentId' => $paymentId,
                'chargeId' => $response->getChargeId(),
            ]);
        }
    }

    /**
     * @throws OrderChargeException
     */
    public function partialCharge(OrderEntity $order, ChargeData $chargeData): void
    {
        $salesChannelId = $order->getSalesChannelId();
        if ($this->configurationProvider->isAutoCharge($salesChannelId)) {
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

            $payment = $this->fetcher->fetchPayment($salesChannelId, $paymentId);


            if (!$this->isChargeable($payment, $chargeData->getAmount())) {
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
                $paymentApi = $this->createPaymentApi($salesChannelId);
                $response = $paymentApi->charge($paymentId, $payload);
            } catch (PaymentApiException $e) {
                $this->logger->error('Partial charge failed', [
                    'paymentId' => $paymentId,
                    'error' => $e->getMessage(),
                ]);

                throw new OrderChargeException($paymentId, previous: $e);
            }

            $this->logger->info('Partial charge success', [
                'paymentId' => $paymentId,
                'chargeId' => $response->getChargeId(),
            ]);
        }
    }

    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        return $this->apiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId)
        );
    }

    private function isChargeable(Payment $payment, float $partialAmount): bool
    {
        $status = $payment->getStatus();

        if (\in_array(
            $status,
            [
                PaymentStatusEnum::RESERVED,
                PaymentStatusEnum::PARTIALLY_CHARGED,
            ],
            true
        )) {
            return true;
        }

        return $status === PaymentStatusEnum::PARTIALLY_REFUNDED
            && $payment->getSummary()->getChargedAmount() > $partialAmount;
    }
}
