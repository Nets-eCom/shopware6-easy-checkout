<?php

declare(strict_types=1);

namespace Nexi\Checkout\Order;

use Nexi\Checkout\Administration\Model\ChargeData;
use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Core\Content\NexiCheckout\Event\ChargeSend;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Order\Exception\OrderChargeException;
use Nexi\Checkout\RequestBuilder\ChargeRequest;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class OrderCharge
{
    public function __construct(
        private readonly PaymentFetcherInterface $fetcher,
        private readonly PaymentApiFactory $apiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly ChargeRequest $chargeRequest,
        private readonly EventDispatcherInterface $dispatcher,
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

        $paymentApi = $this->createPaymentApi($salesChannelId);

        /** @var OrderTransactionEntity $transaction */
        foreach ($transactions as $transaction) {
            $paymentId = $transaction->getCustomFieldsValue(
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID
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

            $this->dispatcher->dispatch(
                new ChargeSend($order, $transaction)
            );
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

        $paymentApi = $this->createPaymentApi($salesChannelId);

        /** @var OrderTransactionEntity $transaction */
        foreach ($transactions as $transaction) {
            $paymentId = $transaction->getCustomFieldsValue(
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID
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

            $this->dispatcher->dispatch(
                new ChargeSend($order, $transaction)
            );
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
