<?php declare(strict_types=1);

namespace NexiNets\Fetcher;

use NexiNets\CheckoutApi\Api\Exception\PaymentApiException;
use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\Configuration\ConfigurationProvider;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;

class PaymentFetcher implements PaymentFetcherInterface, CachablePaymentFetcherInterface
{
    public function __construct(
        private readonly PaymentApiFactory $paymentApiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly CacheInterface $cache
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
        return $this->cache->get(
            $paymentId,
            fn (): Payment => $this->fetchPayment($salesChannelId, $paymentId)
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function removeCache(string $paymentId): void
    {
        $this->cache->delete($paymentId);
    }

    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        return $this->paymentApiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId),
        );
    }
}
