<?php declare(strict_types=1);

namespace Nexi\Checkout\Tests\RequestBuilder\PaymentRequest;

use Nexi\Checkout\RequestBuilder\Helper\FormatHelper;
use Nexi\Checkout\RequestBuilder\PaymentRequest\ItemsBuilder;
use NexiCheckout\Model\Request\Item;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

final class ItemsBuilderTest extends TestCase
{
    private const CART_TOKEN = 'token';

    public function testItCreateFromOrder(): void
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

        $sut = new ItemsBuilder(new FormatHelper());

        $result = $sut->createFromOrder($orderEntity);

        $this->assertCount(3, $result);
        $this->assertContainsOnlyInstancesOf(Item::class, $result);

        $item0Array = $result[0]->jsonSerialize();
        $this->assertEquals(1, $item0Array['quantity']);
        $this->assertEquals(800, $item0Array['unitPrice']);
        $this->assertEquals(1000, $item0Array['grossTotalAmount']);
        $this->assertEquals(800, $item0Array['netTotalAmount']);
        $this->assertEquals(200, $item0Array['taxAmount']);

        $item1Array = $result[1]->jsonSerialize();
        $this->assertEquals(2, $item1Array['quantity']);
        $this->assertEquals(800, $item1Array['unitPrice']);
        $this->assertEquals(1998, $item1Array['grossTotalAmount']);
        $this->assertEquals(1600, $item1Array['netTotalAmount']);
        $this->assertEquals(398, $item1Array['taxAmount']);

        $item2Array = $result[2]->jsonSerialize();
        $this->assertEquals(1, $item2Array['quantity']);
        $this->assertEquals(-500, $item2Array['unitPrice']);
        $this->assertEquals(-500, $item2Array['grossTotalAmount']);
        $this->assertEquals(-500, $item2Array['netTotalAmount']);
        $this->assertArrayNotHasKey('taxAmount', $item2Array);
    }

    public function testItCreatesFromOrderTaxNet(): void
    {
        $orderEntity = new OrderEntity();
        $orderEntity->setId('1234');
        $orderEntity->setTaxStatus(CartPrice::TAX_STATE_NET);

        $orderItems = new OrderLineItemCollection([
            $this->createOrderItemEntity('item1', 10.0, 2, 2.0, false),
        ]);
        $orderEntity->setLineItems($orderItems);

        $sut = new ItemsBuilder(new FormatHelper());

        $result = $sut->createFromOrder($orderEntity);

        $this->assertCount(1, $result);
        $this->assertContainsOnlyInstancesOf(Item::class, $result);

        $item0Array = $result[0]->jsonSerialize();
        $this->assertEquals(2, $item0Array['quantity']);
        $this->assertEquals(500, $item0Array['unitPrice']);
        $this->assertEquals(1200, $item0Array['grossTotalAmount']);
        $this->assertEquals(1000, $item0Array['netTotalAmount']);
        $this->assertEquals(200, $item0Array['taxAmount']);
    }

    public function testItCreatesItemsFromCart(): void
    {
        $cart = new Cart(self::CART_TOKEN);
        $cart->addLineItems(new LineItemCollection([
            $this->createLineItem('foo', 10, 1, 1),
        ]));

        $sut = new ItemsBuilder(new FormatHelper());
        $result = $sut->createFromCart($cart);

        $this->assertCount(1, $result);
        $this->assertContainsOnlyInstancesOf(Item::class, $result);

        $item = $result[0];
        $this->assertEquals(1, $item->getQuantity());
        $this->assertEquals(900, $item->getUnitPrice());
        $this->assertEquals(1000, $item->getGrossTotalAmount());
        $this->assertEquals(900, $item->getNetTotalAmount());
        $this->assertEquals(100, $item->getTaxAmount());
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

    private function createLineItem(
        string $identifier,
        float $price,
        int $quantity,
        float $tax,
        bool $taxIncl = true
    ): LineItem {
        $lineItem = new LineItem($identifier, LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setLabel($identifier);

        $calculatedPrice = new CalculatedPrice(
            $price / $quantity,
            $taxIncl ? $price : $price + $tax,
            new CalculatedTaxCollection(
                [new CalculatedTax($tax, $tax / ($price - $tax), $price - $tax)]
            ),
            new TaxRuleCollection(),
            $quantity
        );
        $lineItem->setPrice($calculatedPrice);

        return $lineItem;
    }
}
