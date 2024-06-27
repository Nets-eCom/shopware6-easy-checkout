<?php

declare(strict_types=1);

namespace NexiNets\Tests\Lifecycle;

use NexiNets\Handler\HostedPayment;
use NexiNets\Lifecycle\HostedPaymentMethodInstaller;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

final class HostedPaymentMethodInstallerTest extends TestCase
{
    public function testInstall(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->createPaymentMethodRepository(
            $this->createSearchResult($context, 0, [])
        );
        $repository
            ->expects($this->once())
            ->method('upsert')
            ->with($this->isType('array'), $context);

        $sut = new HostedPaymentMethodInstaller(
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
                        'name' => 'NexiNets Hosted Checkout',
                        'description' => 'NexiNets Hosted Checkout',
                        'technicalName' => 'nexi_nets_hosted_checkout',
                        'pluginId' => '',
                    ],
                ],
                $context
            );

        $sut = new HostedPaymentMethodInstaller(
            $this->createMock(PluginIdProvider::class),
            $repository
        );

        $sut->install($context);
    }

    /**
     * @return MockObject|EntityRepository<PaymentMethodCollection>
     */
    private function createPaymentMethodRepository(
        IdSearchResult $idSearchResult
    ): MockObject|EntityRepository {
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->method('searchIds')
            ->with(
                (new Criteria())
                    ->addFilter(
                        new EqualsFilter('handlerIdentifier', HostedPayment::class)
                    )
            )
            ->willReturn($idSearchResult);

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
