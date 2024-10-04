<?php declare(strict_types=1);

namespace NexiNets\Fetcher;

use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\Configuration\ConfigurationProvider;

class PaymentFetcher implements PaymentFetcherInterface
{
    public function __construct(
        private readonly PaymentApiFactory $paymentApiFactory,
        private readonly ConfigurationProvider $configurationProvider,
    ) {
    }

    public function fetchPayment(string $salesChannelId, string $paymentId): Payment
    {
        return $this
            ->createPaymentApi($salesChannelId)
            ->retrievePayment($paymentId)
            ->getPayment();
    }

    public function getCachedPayment(string $salesChannelId, string $paymentId): Payment
    {
        // @todo Implement caching
        return $this->fetchPayment($salesChannelId, $paymentId);
    }

    public function updateCache(string $salesChannelId, string $paymentId): void
    {
        // @todo Implement caching
    }

    private function createPaymentApi(string $salesChannelId): PaymentApi
    {
        return $this->paymentApiFactory->create(
            $this->configurationProvider->getSecretKey($salesChannelId),
            $this->configurationProvider->isLiveMode($salesChannelId),
        );
    }
}
