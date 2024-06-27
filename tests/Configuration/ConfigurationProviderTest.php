<?php

declare(strict_types=1);

namespace NexiNets\Tests\Configuration;

use NexiNets\Configuration\ConfigurationProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

final class ConfigurationProviderTest extends TestCase
{
    public function testItProvidesSecretKey(): void
    {
        $sut = new ConfigurationProvider($this->createSystemConfigService());

        $this->assertSame('1111', $sut->getSecretKey());
        $this->assertSame('5555', $sut->getSecretKey('primary'));
        $this->assertSame('1212', $sut->getSecretKey('secondary'));
    }

    public function testItProvidesCheckoutKey(): void
    {
        $sut = new ConfigurationProvider($this->createSystemConfigService());

        $this->assertSame('2222', $sut->getCheckoutKey());
        $this->assertSame('6666', $sut->getCheckoutKey('primary'));
        $this->assertSame('1313', $sut->getCheckoutKey('secondary'));
    }

    private function createSystemConfigService(): StaticSystemConfigService
    {
        return new StaticSystemConfigService([
            ConfigurationProvider::LIVE_SECRET_KEY => '1111',
            ConfigurationProvider::LIVE_CHECKOUT_KEY => '2222',
            ConfigurationProvider::TEST_SECRET_KEY => '3333',
            ConfigurationProvider::TEST_CHECKOUT_KEY => '4444',
            ConfigurationProvider::LIVE_MODE => true,
            'primary' => [
                ConfigurationProvider::LIVE_SECRET_KEY => '5555',
                ConfigurationProvider::LIVE_CHECKOUT_KEY => '6666',
                ConfigurationProvider::TEST_SECRET_KEY => '7777',
                ConfigurationProvider::TEST_CHECKOUT_KEY => '8888',
                ConfigurationProvider::LIVE_MODE => true,
            ],
            'secondary' => [
                ConfigurationProvider::LIVE_SECRET_KEY => '9999',
                ConfigurationProvider::LIVE_CHECKOUT_KEY => '0000',
                ConfigurationProvider::TEST_SECRET_KEY => '1212',
                ConfigurationProvider::TEST_CHECKOUT_KEY => '1313',
                ConfigurationProvider::LIVE_MODE => false,
            ],
        ]);
    }
}
