<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Mcp;

use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Mcp\NexiTransactionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

final class NexiTransactionResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function identifiers(): iterable
    {
        yield 'order ID' => ['order-id', '', ''];
        yield 'order number' => ['', '10001', ''];
        yield 'payment ID' => ['', '', 'payment-id'];
    }

    #[DataProvider('identifiers')]
    public function testItResolvesEachSupportedIdentifier(
        string $orderId,
        string $orderNumber,
        string $paymentId,
    ): void {
        $transaction = $this->createResolver($this->createOrder(['payment-id']))->resolve(
            $orderId,
            $orderNumber,
            $paymentId
        );

        $this->assertSame('order-id', $transaction->getOrder()->getId());
        $this->assertSame('transaction-0', $transaction->getId());
        $this->assertSame(
            'payment-id',
            $transaction->getCustomFieldsValue(OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID)
        );
    }

    public function testItRequiresExactlyOneIdentifier(): void
    {
        $resolver = $this->createResolver($this->createOrder(['payment-id']));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly one');

        $resolver->resolve('order-id', '10001');
    }

    public function testItReturnsFirstOrderPayment(): void
    {
        $transaction = $this->createResolver($this->createOrder(['payment-1', 'payment-2']))->resolve(
            orderId: 'order-id'
        );

        $this->assertSame(
            'payment-1',
            $transaction->getCustomFieldsValue(OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID)
        );
        $this->assertSame('transaction-0', $transaction->getId());
    }

    /**
     * @param list<string> $paymentIds
     */
    private function createOrder(array $paymentIds): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setOrderNumber('10001');

        $transactions = [];
        foreach ($paymentIds as $index => $paymentId) {
            $transaction = new OrderTransactionEntity();
            $transaction->setId('transaction-' . $index);
            $transaction->setOrder($order);
            $transaction->setCustomFields([
                OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID => $paymentId,
            ]);
            $transactions[] = $transaction;
        }

        $order->setTransactions(new OrderTransactionCollection($transactions));

        return $order;
    }

    private function createResolver(OrderEntity $order): NexiTransactionResolver
    {
        $result = $this->createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new OrderCollection([$order]));

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);
        $contextProvider = $this->createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        return new NexiTransactionResolver($repository, $contextProvider);
    }
}
