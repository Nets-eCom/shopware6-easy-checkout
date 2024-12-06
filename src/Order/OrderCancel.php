<?php

declare(strict_types=1);

namespace NexiNets\Order;

use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentStatusEnum;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\Fetcher\PaymentFetcherInterface;
use NexiNets\Order\Exception\OrderCancelException;
use NexiNets\RequestBuilder\CancelRequest;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class OrderCancel
{
    public function __construct(
        private readonly PaymentFetcherInterface $fetcher,
        private readonly PaymentApiFactory $apiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly CancelRequest $requestBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws OrderCancelException
     */
    public function cancel(OrderEntity $order): void
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

            if ($payment->getStatus() !== PaymentStatusEnum::RESERVED) {
                continue;
            }

            $api = $this->createPaymentApi($order->getSalesChannelId());
            $payload = $this->requestBuilder->build($transaction);

            $this->logger->error('Cancel request', [
                'paymentId' => $paymentId,
                'payload' => $payload,
            ]);

            try {
                $api->cancel(
                    $paymentId,
                    $this->requestBuilder->build($transaction)
                );
            } catch (PaymentApiException $e) {
                $this->logger->error('Cancel request failed', [
                    'paymentId' => $paymentId,
                    'payload' => $payload,
                ]);

                throw new OrderCancelException($paymentId, previous: $e);
            }

            $this->logger->error('Cancel request success', [
                'paymentId' => $paymentId,
                'payload' => $payload,
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
}
