<?php declare(strict_types=1);

namespace NexiNets\Fetcher;

use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\Configuration\ConfigurationProvider;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

class PaymentFetcher implements PaymentFetcherInterface, CachablePaymentFetcherInterface
{
    public function __construct(
        private readonly PaymentApiFactory $paymentApiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly CacheItemPoolInterface $cache
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
