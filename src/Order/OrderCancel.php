<?php

declare(strict_types=1);

namespace Nexi\Checkout\Order;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Core\Content\NexiCheckout\Event\CancelSend;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Fetcher\PaymentFetcherInterface;
use Nexi\Checkout\Order\Exception\OrderCancelException;
use Nexi\Checkout\RequestBuilder\CancelRequest;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Result\RetrievePayment\PaymentStatusEnum;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class OrderCancel
{
    public function __construct(
        private readonly PaymentFetcherInterface $fetcher,
        private readonly PaymentApiFactory $apiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly CancelRequest $requestBuilder,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
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

        $paymentApi = $this->createPaymentApi($order->getSalesChannelId());

        /** @var OrderTransactionEntity $transaction */
        foreach ($transactions as $transaction) {
            $paymentId = $transaction->getCustomFieldsValue(
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID
            );

            if ($paymentId === null) {
                continue;
            }

            $payment = $this->fetcher->fetchPayment($order->getSalesChannelId(), $paymentId);

            if ($payment->getStatus() !== PaymentStatusEnum::RESERVED) {
                continue;
            }

            $payload = $this->requestBuilder->build($transaction);

            $this->logger->info('Cancel request', [
                'paymentId' => $paymentId,
                'payload' => $payload,
            ]);

            try {
                $paymentApi->cancel(
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

            $this->logger->info('Cancel request success', [
                'paymentId' => $paymentId,
                'payload' => $payload,
            ]);

            $this->dispatcher->dispatch(
                new CancelSend($order, $transaction)
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
}
