<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Mcp;

use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Helper\FormatHelper;
use Nexi\Checkout\Mcp\NexiOrderResolver;
use Nexi\Checkout\RequestBuilder\ChargeRequest\ItemsBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

final class NexiOrderResolverTest extends TestCase
{
    public function testItResolvesEachSupportedIdentifier(): void
    {
        foreach ([['order-id', '', ''], ['', '10001', ''], ['', '', 'payment-1']] as $identifiers) {
            $order = $this->createResolver()->resolve(...$identifiers);
            $this->assertSame('order-id', $order->getId());
            $this->assertCount(1, $order->getTransactions());
            $this->assertSame('transaction-0', $order->getTransactions()->first()->getId());
            $this->assertSame('10001', $order->getTransactions()->first()->getOrder()->getOrderNumber());
        }
    }

    public function testItRequiresExactlyOneIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly one');
        $this->createResolver()->resolve('order-id', '10001');
    }

    public function testItScopesTheOrderToTheSelectedPayment(): void
    {
        $order = $this->createResolver()->resolve(paymentId: 'payment-2');
        $this->assertCount(1, $order->getTransactions());
        $this->assertSame('transaction-1', $order->getTransactions()->first()->getId());
        $this->assertSame('sales-channel-id', $order->getSalesChannelId());
        $this->assertNull($order->getTransactions()->first()->getOrder()->getTransactions());
        $builder = new ItemsBuilder(new FormatHelper());
        $this->assertCount(1, $builder->createUnrelatedPartialChargeItem($order->getTransactions()->first(), 5.0));
    }

    public function testItRejectsAPaymentThatDisappearsDuringResolution(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no longer available');
        $this->createResolver(true)->resolve(orderNumber: '10001');
    }

    /**
     * @param array<int, string> $paymentIds
     */
    private function createOrder(array $paymentIds): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setOrderNumber('10001');
        $order->setSalesChannelId('sales-channel-id');

        $transactions = new OrderTransactionCollection();
        foreach ($paymentIds as $index => $paymentId) {
            $transaction = new OrderTransactionEntity();
            $transaction->setId('transaction-' . $index);
            $transaction->setCustomFields([
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID => $paymentId,
            ]);
            $nestedOrder = new OrderEntity();
            $nestedOrder->setId('order-id');
            $nestedOrder->setOrderNumber('10001');
            $transaction->setOrder($nestedOrder);
            $transactions->add($transaction);
        }

        $order->setTransactions($transactions);

        return $order;
    }

    private function createResolver(bool $disappeared = false): NexiOrderResolver
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(function (Criteria $criteria) use ($disappeared) {
            $result = $this->createStub(EntitySearchResult::class);
            $filters = $criteria->getAssociation('transactions')->getFilters();
            if ($filters === []) {
                $result->method('getEntities')->willReturn(new OrderCollection([
                    $this->createOrder(['payment-1', 'payment-2']),
                ]));

                return $result;
            }

            $this->assertCount(1, $filters);
            $filter = $filters[0];
            $this->assertInstanceOf(EqualsFilter::class, $filter);
            $this->assertSame('id', $filter->getField());
            $this->assertTrue($criteria->hasAssociation('currency'));
            $this->assertTrue($criteria->getAssociation('transactions')->hasAssociation('order'));
            $this->assertFalse($criteria->getAssociation('transactions.order')->hasAssociation('transactions'));
            $index = $filter->getValue() === 'transaction-0' ? 0 : 1;
            $order = $this->createOrder([
                $index => 'payment-' . ($index + 1),
            ]);
            $result->method('first')->willReturn($disappeared ? null : $order);

            return $result;
        });
        $contextProvider = $this->createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createStub(Context::class));

        return new NexiOrderResolver($repository, $contextProvider);
    }
}
