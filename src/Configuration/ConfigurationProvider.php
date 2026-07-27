<?php

declare(strict_types=1);

namespace Nexi\Checkout\Configuration;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigurationProvider
{
    private const CONFIG_DOMAIN = 'NetsNexiCheckout.config.';

    public const LIVE_SECRET_KEY = self::CONFIG_DOMAIN . 'liveSecretKey';

    public const LIVE_CHECKOUT_KEY = self::CONFIG_DOMAIN . 'liveCheckoutKey';

    public const TEST_SECRET_KEY = self::CONFIG_DOMAIN . 'testSecretKey';

    public const TEST_CHECKOUT_KEY = self::CONFIG_DOMAIN . 'testCheckoutKey';

    public const LIVE_MODE = self::CONFIG_DOMAIN . 'liveMode';

    public const AUTO_CHARGE = self::CONFIG_DOMAIN . 'autoCharge';

    public const TERMS_URL = self::CONFIG_DOMAIN . 'termsUrl';

    public const MERCHANT_TERMS_URL = self::CONFIG_DOMAIN . 'merchantTermsUrl';

    public const WEBHOOK_AUTHORIZATION_HEADER = self::CONFIG_DOMAIN . 'webhookAuthorizationHeader';

    public const PAY_TYPE_SPLITTING = self::CONFIG_DOMAIN . 'payTypeSplitting';

    public const PAY_TYPE_OPTIONS = self::CONFIG_DOMAIN . 'payTypeOptions';

    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function getSecretKey(?string $salesChannelId = null): string
    {
        return $this->isLiveMode($salesChannelId) ?
            $this->systemConfigService->get(self::LIVE_SECRET_KEY, $salesChannelId) :
            $this->systemConfigService->get(self::TEST_SECRET_KEY, $salesChannelId);
    }

    public function getCheckoutKey(?string $salesChannelId = null): string
    {
        return $this->isLiveMode($salesChannelId) ?
            $this->systemConfigService->get(self::LIVE_CHECKOUT_KEY, $salesChannelId) :
            $this->systemConfigService->get(self::TEST_CHECKOUT_KEY, $salesChannelId);
    }

    public function getMerchantTermsUrl(?string $salesChannelId = null): string
    {
        return $this->systemConfigService->getString(
            self::MERCHANT_TERMS_URL,
            $salesChannelId
        );
    }

    public function getTermsUrl(?string $salesChannelId = null): string
    {
        return $this->systemConfigService->getString(
            self::TERMS_URL,
            $salesChannelId
        );
    }

    public function getWebhookAuthorizationHeader(?string $salesChannelId = null): string
    {
        return $this->systemConfigService->getString(
            self::WEBHOOK_AUTHORIZATION_HEADER,
            $salesChannelId
        );
    }

    public function isLiveMode(?string $salesChannelId = null): bool
    {
        return $this->systemConfigService->getBool(self::LIVE_MODE, $salesChannelId);
    }

    public function isAutoCharge(?string $salesChannelId = null): bool
    {
        return $this->systemConfigService->getBool(self::AUTO_CHARGE, $salesChannelId);
    }

    public function isPayTypeSplitting(?string $salesChannelId = null): bool
    {
        return $this->systemConfigService->getBool(self::PAY_TYPE_SPLITTING, $salesChannelId);
    }

    /**
     * @return array<int, array<string, string|bool>>
     */
    public function getPayTypeOptions(?string $salesChannelId = null): array
    {
        $raw = $this->systemConfigService->getString(self::PAY_TYPE_OPTIONS, $salesChannelId);

        return json_decode($raw, true) ?? [];
    }
}
