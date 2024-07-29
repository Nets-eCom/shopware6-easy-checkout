<?php declare(strict_types=1);

namespace NexiNets\Tests\RequestBuilder\PaymentRequest;

use NexiNets\CheckoutApi\Model\Request\Payment\Item;
use NexiNets\RequestBuilder\Helper\FormatHelper;
use NexiNets\RequestBuilder\PaymentRequest\ItemsBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Test\Generator;

final class ItemsBuilderTest extends TestCase
{
    public function testCreate(): void
    {
        $orderEntity = new OrderEntity();
        $orderEntity->setId('1234');
        $orderEntity->setTaxStatus(CartPrice::TAX_STATE_GROSS);

        $orderItems = new OrderLineItemCollection([
            $this->createOrderItemEntity('item1', 10.0, 1, 2),
            $this->createOrderItemEntity('item2', 19.98, 2, 3.98),
            $this->createOrderItemEntity('discount1', -5.0, 1, 0),
        ]);
        $orderEntity->setLineItems($orderItems);

        $salesChannelContext = Generator::createSalesChannelContext();

        $object = new ItemsBuilder(new FormatHelper());

        $items = $object->create($orderEntity, $salesChannelContext);

        $this->assertCount(3, $items);
        $this->assertContainsOnlyInstancesOf(Item::class, $items);

        $item0Array = $items[0]->jsonSerialize();
        $this->assertEquals(1, $item0Array['quantity']);
        $this->assertEquals(800, $item0Array['unitPrice']);
        $this->assertEquals(1000, $item0Array['grossTotalAmount']);
        $this->assertEquals(800, $item0Array['netTotalAmount']);
        $this->assertEquals(200, $item0Array['taxAmount']);

        $item1Array = $items[1]->jsonSerialize();
        $this->assertEquals(2, $item1Array['quantity']);
        $this->assertEquals(800, $item1Array['unitPrice']);
        $this->assertEquals(1998, $item1Array['grossTotalAmount']);
        $this->assertEquals(1600, $item1Array['netTotalAmount']);
        $this->assertEquals(398, $item1Array['taxAmount']);

        $item2Array = $items[2]->jsonSerialize();
        $this->assertEquals(1, $item2Array['quantity']);
        $this->assertEquals(-500, $item2Array['unitPrice']);
        $this->assertEquals(-500, $item2Array['grossTotalAmount']);
        $this->assertEquals(-500, $item2Array['netTotalAmount']);
        $this->assertArrayNotHasKey('taxAmount', $item2Array);
    }

    public function testCreateTaxNet(): void
    {
        $orderEntity = new OrderEntity();
        $orderEntity->setId('1234');
        $orderEntity->setTaxStatus(CartPrice::TAX_STATE_NET);

        $orderItems = new OrderLineItemCollection([
            $this->createOrderItemEntity('item1', 10.0, 2, 2.0, false),
        ]);
        $orderEntity->setLineItems($orderItems);

        $salesChannelContext = Generator::createSalesChannelContext();

        $object = new ItemsBuilder(new FormatHelper());

        $items = $object->create($orderEntity, $salesChannelContext);

        $this->assertCount(1, $items);
        $this->assertContainsOnlyInstancesOf(Item::class, $items);

        $item0Array = $items[0]->jsonSerialize();
        $this->assertEquals(2, $item0Array['quantity']);
        $this->assertEquals(500, $item0Array['unitPrice']);
        $this->assertEquals(1200, $item0Array['grossTotalAmount']);
        $this->assertEquals(1000, $item0Array['netTotalAmount']);
        $this->assertEquals(200, $item0Array['taxAmount']);
    }

    private function createOrderItemEntity(
        string $identifier,
        float $price,
        int $quantity,
        float $tax,
        bool $taxIncl = true
    ): OrderLineItemEntity {
        $orderItem = new OrderLineItemEntity();

        $orderItem->setId($identifier);
        $orderItem->setLabel($identifier);
        $orderItem->setPosition(0);
        $orderItem->setQuantity($quantity);

        $calculatedPrice = new CalculatedPrice(
            $price / $quantity,
            $taxIncl ? $price : $price + $tax,
            new CalculatedTaxCollection(
                [new CalculatedTax($tax, $tax / ($price - $tax), $price - $tax)]
            ),
            new TaxRuleCollection(),
            $quantity
        );
        $orderItem->setPrice($calculatedPrice);

        return $orderItem;
    }
}
