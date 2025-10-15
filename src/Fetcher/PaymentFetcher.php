<?php

declare(strict_types=1);

namespace Nexi\Checkout\Fetcher;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Api\PaymentApi;
use NexiCheckout\Factory\PaymentApiFactory;
use NexiCheckout\Model\Result\RetrievePayment\Payment;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

readonly class PaymentFetcher implements PaymentFetcherInterface, CachablePaymentFetcherInterface
{
    public function __construct(
        private PaymentApiFactory $paymentApiFactory,
        private ConfigurationProvider $configurationProvider,
        private CacheItemPoolInterface $cache
    ) {
    }

    /**
     * @throws PaymentApiException
     */
    public function fetchPayment(string $salesChannelId, string $paymentId): Payment
    {
        return $this
            ->createPaymentApi($salesChannelId)
            ->retrievePayment($paymentId)
            ->getPayment();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getCachedPayment(string $salesChannelId, string $paymentId): Payment
    {
        $item = $this->cache->getItem($paymentId);
        $payment = $item->get();

        if (!$item->isHit() || $payment === null) {
            $payment = $this->fetchPayment($salesChannelId, $paymentId);

            $item->set($payment);
            $this->cache->save($item);

            return $payment;
        }

        return $payment;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function removeCache(string $paymentId): void
    {
        if (!$this->cache->hasItem($paymentId)) {
            return;
        }

        $this->cache->deleteItem($paymentId);
    }

    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        return $this->paymentApiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId),
        );
    }
}
