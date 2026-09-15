<?php

declare(strict_types=1);

namespace Nexi\Checkout\Http;

use Nexi\Checkout\NetsNexiCheckout;
use NexiCheckout\Factory\Provider\HttpClientConfigurationProvider;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class HttpClientProviderConfigurator
{
    public function __construct(
        private HttpClientConfigurationProvider $provider,
        private string $shopwareVersion,
        private RequestStack $requestStack,
    ) {
    }

    public function configure(): void
    {
        $this->provider->setCommercePlatformTag($this->buildCommerceTag());
    }

    private function buildCommerceTag(): string
    {
        $tag = \sprintf(
            '%s %s, %s, php%s',
            NetsNexiCheckout::COMMERCE_PLATFORM_TAG,
            $this->shopwareVersion,
            NetsNexiCheckout::PLUGIN_VERSION,
            \PHP_VERSION
        );

        if (!$this->isMcpContext()) {
            return $tag;
        }

        return \sprintf('%s, origin=mcp', $tag);
    }

    public function isMcpContext(): bool
    {
        return $this->requestStack->getMainRequest()?->attributes->get('_route') === 'api.mcp.endpoint';
    }
}
