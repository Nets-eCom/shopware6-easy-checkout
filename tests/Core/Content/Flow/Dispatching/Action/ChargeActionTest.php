<?php declare(strict_types=1);

namespace Nexi\Checkout\Tests\Core\Content\Flow\Dispatching\Action;

use Nexi\Checkout\Core\Content\Flow\Dispatching\Action\ChargeAction;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Order\OrderCharge;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\OrderAware;

final class ChargeActionTest extends TestCase
{
    public function testHandleFlowShouldCharge(): void
    {
        $order = $this->createOrderEntity();
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction_uuid');
        $transaction->setCustomFields([
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID => '025400006091b1ef6937598058c4e487',
        ]);
        $order->getTransactions()->add($transaction);

        $orderCharge = $this->createMock(OrderCharge::class);
        $orderCharge->expects($this->once())->method('fullCharge');

        $sut = new ChargeAction($orderCharge);

        $sut->handleFlow($this->createStorableFlow($order));
    }

    private function createOrderEntity(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order_uuid');
        $order->setSalesChannelId('test_sales_channel_id');
        $order->setTransactions(new OrderTransactionCollection([]));

        return $order;
    }

    private function createStorableFlow(OrderEntity $order): StorableFlow
    {
        return new StorableFlow(
            'test',
            Context::createDefaultContext(),
            [],
            [
                OrderAware::ORDER => $order,
            ]
        );
    }
}
