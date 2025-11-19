<?php

declare(strict_types=1);

namespace Nexi\Checkout\Http;

use Nexi\Checkout\NetsNexiCheckout;
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
            NetsNexiCheckout::COMMERCE_PLATFORM_TAG,
            $this->shopwareVersion,
            NetsNexiCheckout::PLUGIN_VERSION,
            \PHP_VERSION
        );
    }
}
