<?php

declare(strict_types=1);

namespace Nexi\Checkout\Http;

use Nexi\Checkout\NexiCheckout;
use NexiCheckout\Factory\Provider\HttpClientConfigurationProvider;

readonly class HttpClientProviderConfigurator
{
    public function __construct(
        private HttpClientConfigurationProvider $provider,
        private string $shopwareVersion
    ) {
    }

    public function configure(): void
    {
        $this->provider->setCommercePlatformTag($this->buildCommerceTag());
    }

    private function buildCommerceTag(): string
    {
        return \sprintf(
            '%s %s, %s, php%s',
            NexiCheckout::COMMERCE_PLATFORM_TAG,
            $this->shopwareVersion,
            NexiCheckout::PLUGIN_VERSION,
            \PHP_VERSION
        );
    }
}
