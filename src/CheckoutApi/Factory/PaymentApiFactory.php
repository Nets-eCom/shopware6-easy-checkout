<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Factory;

use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\Provider\HttpClientConfigurationProviderInterface;

class PaymentApiFactory
{
    private const LIVE_URL = 'https://api.dibspayment.eu';

    private const TEST_URL = 'https://test.api.dibspayment.eu';

    public function __construct(
        private readonly HttpClientFactory $clientFactory,
        private readonly HttpClientConfigurationProviderInterface $configurationProvider,
    ) {
    }

    public function create(
        string $secretKey,
        bool $isLiveMode,
    ): PaymentApi {
        return new PaymentApi(
            $this
                ->clientFactory
                ->create(
                    $this->configurationProvider->provide($secretKey)
                ),
            $this->getBaseUrl($isLiveMode),
        );
    }

    private function getBaseUrl(bool $isLiveMode): string
    {
        return $isLiveMode ? self::LIVE_URL : self::TEST_URL;
    }
}
