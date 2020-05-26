<?php
namespace Nets\Checkout\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ConfigService
{

    const CONFIG_PREFIX = 'NetsCheckout.config.';

   /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * ConfigService constructor.
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
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

    /**
     * @param $salesChannelContextId
     * @return array|mixed|null|string
     */
    public function getEnvironment($salesChannelContextId) {
        return $this->systemConfigService->get( self::CONFIG_PREFIX  . 'enviromnent', $salesChannelContextId);
    }

    /**
     * @param $salesChannelContextId
     * @return array|mixed|null|string
     */
    public function getLanguage($salesChannelContextId) {
        return $this->systemConfigService->get( self::CONFIG_PREFIX  . 'language', $salesChannelContextId);
    }

}
