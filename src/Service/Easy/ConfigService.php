<?php

namespace Nets\Checkout\Service\Easy;

use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigService
{
    private const CONFIG_PREFIX = 'NetsCheckout.config.';
    private const ENVIRONMENT = 'enviromnent';
    private const CHECKOUT_TYPE = 'checkoutType';
    private const TERMS_URL = 'termsUrl';
    private const MERCHANT_TERMS_URL = 'merchantTermsUrl';
    private const CHARGE_NOW = 'autoCharge';

    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function getSecretKey(?string $salesChannelId = null): ?string
    {
        $env = 'testSecretKey';

        if ($this->getEnvironment($salesChannelId) === EasyApiService::ENV_LIVE) {
            $env = 'liveSecretKey';
        }

        return $this->systemConfigService->get(self::CONFIG_PREFIX . $env, $salesChannelId);
    }

    public function getCheckoutKey(?string $salesChannelId = null): ?string
    {
        $env = 'testCheckoutKey';

        if ($this->getEnvironment($salesChannelId) === EasyApiService::ENV_LIVE) {
            $env = 'liveCheckoutKey';
        }

        return $this->systemConfigService->get(self::CONFIG_PREFIX . $env, $salesChannelId);
    }

    public function getEnvironment(?string $salesChannelId = null): ?string
    {
        return $this->systemConfigService->get(self::CONFIG_PREFIX . self::ENVIRONMENT, $salesChannelId);
    }

    public function getCheckoutType(?string $salesChannelId = null): ?string
    {
        return $this->systemConfigService->get(self::CONFIG_PREFIX . self::CHECKOUT_TYPE, $salesChannelId);
    }

    public function getTermsAndConditionsUrl(?string $salesChannelId = null): ?string
    {
        return $this->systemConfigService->get(self::CONFIG_PREFIX . self::TERMS_URL, $salesChannelId);
    }

    public function getMerchantTermsUrl(?string $salesChannelId = null): ?string
    {
        return $this->systemConfigService->get(self::CONFIG_PREFIX . self::MERCHANT_TERMS_URL, $salesChannelId);
    }

    public function getChargeNow(?string $salesChannelId = null): ?string
    {
        return $this->systemConfigService->get(self::CONFIG_PREFIX . self::CHARGE_NOW, $salesChannelId);
    }
}
