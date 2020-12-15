<?php
namespace Nets\Checkout\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigService
{
    const CONFIG_PREFIX = 'NetsCheckout.config.';

    const ENVIRONMENT = 'enviromnent';
    const LANGUAGE = 'language';
    const CHECKOUT_TYPE = 'checkoutType';
    const TERMS_URL = 'termsUrl';

   /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var
     */
    private $prefix;

    /**
     * ConfigService constructor.
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
        $this->prefix = self::CONFIG_PREFIX;
    }

    /**
     * @param $salesChannelContextId
     * @return array|mixed|null|string
     */
    public function getSecretKey($salesChannelContextId) {
        $env = 'testSecretKey';
        if('live' == $this->getEnvironment($salesChannelContextId)) {
            $env = 'liveSecretKey';
        }
        return $this->systemConfigService->get( self::CONFIG_PREFIX . $env, $salesChannelContextId);
    }

    public function getCheckoutKey($salesChannelContextId) {
       $env = 'testCheckoutKey';
       if('live' == $this->getEnvironment($salesChannelContextId)) {
           $env = 'liveCheckoutKey';
       }
       return $this->systemConfigService->get( self::CONFIG_PREFIX . $env, $salesChannelContextId);
    }

    /**
     * @param $salesChannelContextId
     * @return array|mixed|null|string
     */
    public function getEnvironment($salesChannelContextId) {
        return $this->systemConfigService->get(sprintf("$this->prefix%s", self::ENVIRONMENT), $salesChannelContextId);
    }

    /**
     * @param $salesChannelContextId
     * @return array|mixed|null|string
     */
    public function getLanguage($salesChannelContextId) {
        return $this->systemConfigService->get(sprintf("$this->prefix%s", self::LANGUAGE), $salesChannelContextId);
    }

    /**
     * @param $salesChannelContextId
     * @return array|mixed|null
     */
    public function getCheckoutType($salesChannelContextId) {
        return $this->systemConfigService->get(sprintf("$this->prefix%s", self::CHECKOUT_TYPE), $salesChannelContextId);
    }

    /**
     * @param $salesChannelContextId
     * @return array|mixed|null
     */
    public function getTermsAndConditionsUrl($salesChannelContextId) {
         return $this->systemConfigService->get(sprintf("$this->prefix%s", self::TERMS_URL), $salesChannelContextId);
    }
}
