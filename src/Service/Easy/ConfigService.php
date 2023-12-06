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

    public function getSecretKey(): ?string
    {
        $env = 'testSecretKey';

        if ($this->getEnvironment() === EasyApiService::ENV_LIVE) {
            $env = 'liveSecretKey';
        }

        return $this->systemConfigService->get(self::CONFIG_PREFIX . $env);
    }

    public function getCheckoutKey(): ?string
    {
        $env = 'testCheckoutKey';

        if ($this->getEnvironment() === EasyApiService::ENV_LIVE) {
            $env = 'liveCheckoutKey';
        }

        return $this->systemConfigService->get(self::CONFIG_PREFIX . $env);
    }

    public function getEnvironment(): ?string
    {
        return $this->systemConfigService->get(self::CONFIG_PREFIX . self::ENVIRONMENT);
    }

    public function getCheckoutType(): ?string
    {
        return $this->systemConfigService->get(self::CONFIG_PREFIX . self::CHECKOUT_TYPE);
    }

    public function getTermsAndConditionsUrl(): ?string
    {
        return $this->systemConfigService->get(self::CONFIG_PREFIX . self::TERMS_URL);
    }

    public function getMerchantTermsUrl(): ?string
    {
        return $this->systemConfigService->get(self::CONFIG_PREFIX . self::MERCHANT_TERMS_URL);
    }

    public function getChargeNow(): ?string
    {
        return $this->systemConfigService->get(self::CONFIG_PREFIX . self::CHARGE_NOW);
    }
}
