<?php

declare(strict_types=1);

namespace Nexi\Checkout\PaymentMethods;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class CachedPaymentMethodsFetcher implements PaymentMethodsFetcherInterface
{
    private const CACHE_KEY_PREFIX = 'nexi_payment_methods_';

    private const CACHE_LIFETIME = 60 * 60 * 12; // 12h

    public function __construct(
        private readonly PaymentMethodsFetcher $inner,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<string>
     */
    public function fetchAvailableMethodNames(?string $salesChannelId, ?string $currencyIsoCode = null): array
    {
        $cacheKey = $this->buildCacheKey($salesChannelId, $currencyIsoCode);

        try {
            $item = $this->cache->getItem($cacheKey);

            if ($item->isHit()) {
                /** @var list<string>|null $cached */
                $cached = $item->get();
                if (\is_array($cached)) {
                    return $cached;
                }
            }

            $result = $this->inner->fetchAvailableMethodNames($salesChannelId, $currencyIsoCode);

            if ($result !== []) {
                $item->set($result);
                $item->expiresAfter(self::CACHE_LIFETIME);
                $this->cache->save($item);
            }

            return $result;
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to use cache for payment methods', [
                'message' => $throwable->getMessage(),
            ]);

            return $this->inner->fetchAvailableMethodNames($salesChannelId, $currencyIsoCode);
        }
    }

    public function clear(string $salesChannelId, ?string $currencyIsoCode = null): void
    {
        try {
            $this->cache->deleteItem($this->buildCacheKey($salesChannelId, $currencyIsoCode));
        } catch (\Throwable $throwable) {
            $this->logger->error('Failed to clear payment methods cache', [
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function buildCacheKey(?string $salesChannelId, ?string $currencyIsoCode): string
    {
        $channel = $salesChannelId ?? 'null';
        $currency = $currencyIsoCode !== null ? strtolower($currencyIsoCode) : 'null';

        return self::CACHE_KEY_PREFIX . $channel . '_' . $currency;
    }
}
