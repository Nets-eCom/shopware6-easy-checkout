<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Lifecycle;

use Nexi\Checkout\Handler\EmbeddedPayment;
use Nexi\Checkout\Handler\HostedPayment;
use Nexi\Checkout\Lifecycle\PaymentMethodsInstaller;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

final class PaymentMethodsInstallerTest extends TestCase
{
    public function testInstall(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->createPaymentMethodRepository(
            $this->createSearchResult($context, 0, []),
            $this->createSearchResult($context, 0, [])
        );
        $repository
            ->expects($this->once())
            ->method('upsert')
            ->with($this->isArray(), $context);

        $sut = new PaymentMethodsInstaller(
            $this->createMock(PluginIdProvider::class),
            $repository
        );

        $sut->install($context);
    }

    public function testInstallWhenMethodAlreadyInstalled(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->createPaymentMethodRepository(
            $this->createSearchResult(
                $context,
                1,
                [
                    [
                        'primaryKey' => '1234',
                        'data' => [],
                    ],
                ]
            ),
            $this->createSearchResult(
                $context,
                1,
                [
                    [
                        'primaryKey' => '1235',
                        'data' => [],
                    ],
                ]
            )
        );

        $repository
            ->expects($this->once())
            ->method('upsert')
            ->with(
                [
                    [
                        'id' => '1234',
                        'handlerIdentifier' => HostedPayment::class,
                        'name' => 'Nexi Checkout Hosted',
                        'description' => 'Nexi Checkout Hosted',
                        'technicalName' => 'nexi_checkout_hosted',
                        'afterOrderEnabled' => true,
                        'pluginId' => '',
                    ],
                    [
                        'id' => '1235',
                        'handlerIdentifier' => EmbeddedPayment::class,
                        'name' => 'Nexi Checkout Embedded',
                        'description' => 'Nexi Checkout Embedded',
                        'technicalName' => 'nexi_checkout_embedded',
                        'pluginId' => '',
                    ],
                ],
                $context
            );

        $sut = new PaymentMethodsInstaller(
            $this->createMock(PluginIdProvider::class),
            $repository
        );

        $sut->install($context);
    }

    /**
     * @return MockObject|EntityRepository<PaymentMethodCollection>
     */
    private function createPaymentMethodRepository(
        IdSearchResult $hostedIdSearchResult,
        IdSearchResult $embeddedIdSearchResult
    ): MockObject|EntityRepository {
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->method('searchIds')
            ->willReturnOnConsecutiveCalls($hostedIdSearchResult, $embeddedIdSearchResult);

        return $repository;
    }

    /**
     * @param array<array<string, mixed>> $data
     */
    private function createSearchResult(Context $context, int $total, array $data): IdSearchResult
    {
        return new IdSearchResult(
            $total,
            $data,
            new Criteria(),
            $context
        );
    }
}
