<?php

declare(strict_types=1);

namespace NexiNets\Tests\Lifecycle;

use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Handler\HostedPayment;
use NexiNets\Lifecycle\HostedPaymentMethodActivator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class HostedPaymentMethodActivatorTest extends TestCase
{
    #[DataProvider('activateProvider')]
    public function testActivate(InvokedCount $invokedCount, string $id, int $totalCount, bool $active): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->createPaymentMethodRepository(
            $this->createSearchResult(
                $context,
                $totalCount,
                [
                    [
                        'primaryKey' => $id,
                        'data' => [],
                    ],
                ]
            )
        );

        $repository
            ->expects($invokedCount)
            ->method('update')
            ->with(
                [
                    [
                        'id' => $id,
                        'active' => $active,
                    ],
                ],
                $context
            );

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService
            ->method('getString')
            ->with(ConfigurationProvider::WEBHOOK_AUTHORIZATION_HEADER)
            ->willReturn('');
        $systemConfigService
            ->expects(clone $invokedCount)
            ->method('set')
            ->with(
                ConfigurationProvider::WEBHOOK_AUTHORIZATION_HEADER,
                $this->isType('string')
            );

        $sut = new HostedPaymentMethodActivator(
            $repository,
            $systemConfigService
        );

        $sut->activate($context);
    }

    #[DataProvider('activateProvider')]
    public function testDeactivate(InvokedCount $invokedCount, string $id, int $totalCount, bool $active): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->createPaymentMethodRepository(
            $this->createSearchResult(
                $context,
                $totalCount,
                [
                    [
                        'primaryKey' => $id,
                        'data' => [],
                    ],
                ]
            )
        );

        $repository
            ->expects($invokedCount)
            ->method('update')
            ->with(
                [
                    [
                        'id' => $id,
                        'active' => !$active,
                    ],
                ],
                $context
            );


        $sut = new HostedPaymentMethodActivator(
            $repository,
            $this->createMock(SystemConfigService::class)
        );
        $sut->deactivate($context);
    }

    /**
     * @return iterable<array{InvokedCount, string, int, bool}>
     */
    public static function activateProvider(): iterable
    {
        yield [new InvokedCount(0), '', 0, true];
        yield [new InvokedCount(1), '1234', 1, true];
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
