<?php
namespace Nets\Checkout\Service;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigService
{
    public const CONFIG_PREFIX = 'NetsCheckout.config.';

    public const ENVIRONMENT = 'enviromnent';

    public const LANGUAGE = 'language';

    public const CHECKOUT_TYPE = 'checkoutType';

    public const TERMS_URL = 'termsUrl';

    public const MERCHANT_TERMS_URL = 'merchantTermsUrl';

    public const CHARGE_NOW = 'autoCharge';

    private SystemConfigService $systemConfigService;

    private string $prefix;

    /**
     * ConfigService constructor.
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
        $this->prefix              = self::CONFIG_PREFIX;
    }

    /**
     * @param
     *            $salesChannelContextId
     *
     * @return null|array|mixed|string
     */
    public function getSecretKey($salesChannelContextId)
    {
        $env = 'testSecretKey';

        if ($this->getEnvironment($salesChannelContextId) == 'live') {
            $env = 'liveSecretKey';
        }

        return $this->systemConfigService->get(self::CONFIG_PREFIX . $env, $salesChannelContextId);
    }

    public function getCheckoutKey($salesChannelContextId)
    {
        $env = 'testCheckoutKey';

        if ($this->getEnvironment($salesChannelContextId) == 'live') {
            $env = 'liveCheckoutKey';
        }

        return $this->systemConfigService->get(self::CONFIG_PREFIX . $env, $salesChannelContextId);
    }

    /**
     * @param
     *            $salesChannelContextId
     *
     * @return null|array|mixed|string
     */
    public function getEnvironment($salesChannelContextId)
    {
        return $this->systemConfigService->get(sprintf("$this->prefix%s", self::ENVIRONMENT), $salesChannelContextId);
    }

    /**
     * @param
     *            $salesChannelContextId
     *
     * @return null|array|mixed|string
     */
    public function getLanguage($salesChannelContextId)
    {
        return $this->systemConfigService->get(sprintf("$this->prefix%s", self::LANGUAGE), $salesChannelContextId);
    }

    /**
     * @param
     *            $salesChannelContextId
     *
     * @return null|array|mixed
     */
    public function getCheckoutType($salesChannelContextId)
    {
        return $this->systemConfigService->get(sprintf("$this->prefix%s", self::CHECKOUT_TYPE), $salesChannelContextId);
    }

    /**
     * @param
     *            $salesChannelContextId
     *
     * @return null|array|mixed
     */
    public function getTermsAndConditionsUrl($salesChannelContextId)
    {
        return $this->systemConfigService->get(sprintf("$this->prefix%s", self::TERMS_URL), $salesChannelContextId);
    }

    public function getMerchantTermsUrl($salesChannelContextId)
    {
        return $this->systemConfigService->get(sprintf("$this->prefix%s", self::MERCHANT_TERMS_URL), $salesChannelContextId);
    }

    public function getChargeNow($salesChannelContextId)
    {
        return $this->systemConfigService->get(sprintf("$this->prefix%s", self::CHARGE_NOW), $salesChannelContextId);
    }
}
