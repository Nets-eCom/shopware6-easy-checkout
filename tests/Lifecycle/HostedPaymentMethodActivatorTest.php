<?php

declare(strict_types=1);

namespace NexiNets\Tests\Lifecycle;

use NexiNets\Handler\HostedPayment;
use NexiNets\Lifecycle\HostedPaymentMethodActivator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

final class HostedPaymentMethodActivatorTest extends TestCase
{
    /**
     * @dataProvider activateProvider
     */
    public function testActivate(InvokedCount $invokedCount, string $id, int $totalCount, bool $active): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->createPaymentMethodRepository(
            $this->createSearchResult($context, $totalCount, [['primaryKey' => $id, 'data' => []]])
        );

        $repository
            ->expects($invokedCount)
            ->method('update')
            ->with(
                [
                    [
                        'id' => $id,
                        'active' => $active,
                    ]
                ],
                $context
            );


        $sut = new HostedPaymentMethodActivator($repository);
        $sut->activate($context);
    }

    /**
     * @return iterable<array{InvokedCount, string, int, bool}>
     */
    public function activateProvider(): iterable
    {
        yield [$this->never(), '', 0, true];
        yield [$this->once(), '1234', 1, true];
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