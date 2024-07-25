<?php

declare(strict_types=1);

namespace NexiNets\Http;

use NexiNets\CheckoutApi\Factory\Provider\HttpClientConfigurationProviderInterface;
use NexiNets\CheckoutApi\Http\Configuration;
use NexiNets\NetsCheckout;

final readonly class HttpClientConfigurationProvider implements HttpClientConfigurationProviderInterface
{
    public function __construct(private string $shopwareVersion)
    {
    }

    public function provide(string $secretKey): Configuration
    {
        return new Configuration($secretKey, $this->buildCommerceTag());
    }

    private function buildCommerceTag(): string
    {
        return sprintf(
            '%s %s, %s, php%s',
            NetsCheckout::COMMERCE_PLATFORM_TAG,
            $this->shopwareVersion,
            NetsCheckout::PLUGIN_VERSION,
            \PHP_VERSION
        );
    }
}
