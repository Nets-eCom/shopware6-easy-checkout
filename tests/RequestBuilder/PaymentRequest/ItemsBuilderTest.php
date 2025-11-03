<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\RequestBuilder\PaymentRequest;

use Nexi\Checkout\Helper\FormatHelper;
use Nexi\Checkout\RequestBuilder\PaymentRequest\ItemsBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\StubInternal;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;

final class ItemsBuilderTest extends TestCase
{
    private const CART_TOKEN = 'token';

    /**
     * @param list<OrderLineItemEntity> $orderItems
     * @param list<array<string, int|string>> $expected
     */
    #[DataProvider('orderProvider')]
    public function testCreateFromOrder(string $taxStatus, array $orderItems, ?OrderDeliveryEntity $delivery, array $expected): void
    {
        $sut = new ItemsBuilder(new FormatHelper());

        $order = new OrderEntity();
        $order->setId('order-' . $this->dataName());
        $order->setTaxStatus($taxStatus);
        $order->setLineItems(new OrderLineItemCollection($orderItems));

        if ($delivery instanceof OrderDeliveryEntity) {
            $order->setDeliveries(new OrderDeliveryCollection([$delivery]));
            $order->setShippingCosts($delivery->getShippingCosts());
        }

        $result = $sut->createFromOrder($order);

        $this->assertCount(\count($expected), $result);

        foreach ($expected as $index => $assertion) {
            $this->assertItems($result[$index]->jsonSerialize(), $assertion);
        }
    }

    /**
     * @param list<array{string, float, int, float}> $lineItems
     * @param list<array<string, int|string>> $expected
     */
    #[DataProvider('cartProvider')]
    public function testCreateFromCart(string $taxStatus, array $lineItems, array $expected): void
    {
        $sut = new ItemsBuilder(new FormatHelper());

        $cart = new Cart(self::CART_TOKEN);

        $cartLineItems = [];
        foreach ($lineItems as [$id, $price, $quantity, $tax]) {
            $cartLineItems[] = $this->createLineItem($id, $price, $quantity, $tax);
        }

        $cart->addLineItems(new LineItemCollection($cartLineItems));

        /** @var StubInternal|CartPrice $cartPrice */
        $cartPrice = $this->createStub(CartPrice::class);
        $cartPrice->method('getTaxStatus')->willReturn($taxStatus);

        $cart->setPrice($cartPrice);

        $result = $sut->createFromCart($cart);

        $this->assertCount(\count($expected), $result);

        foreach ($expected as $index => $assertion) {
            $this->assertItems($result[$index]->jsonSerialize(), $assertion);
        }
    }

    /**
     * @return iterable<string, array{string, list<OrderLineItemEntity>, OrderDeliveryEntity|null, list<array<string, int|string>>}>
     */
    public static function orderProvider(): iterable
    {
        yield 'gross-with-shipping-and-discount' => [
            CartPrice::TAX_STATE_GROSS,
            [
                self::createOrderItemEntity('item1', 10.0, 1, 2.0),
                self::createOrderItemEntity('item2', 19.98, 2, 3.98),
                self::createOrderItemEntity('discount1', -5.0, 1, 0.0),
            ],
            self::createOrderDeliveryEntity('shipping1', 2.99, 1, 0.5),
            [
                [
                    'quantity' => 1,
                    'unitPrice' => 800,
                    'grossTotalAmount' => 1000,
                    'netTotalAmount' => 800,
                    'taxAmount' => 200,
                ],
                [
                    'quantity' => 2,
                    'unitPrice' => 800,
                    'grossTotalAmount' => 1998,
                    'netTotalAmount' => 1600,
                    'taxAmount' => 398,
                ],
                [
                    'quantity' => 1,
                    'unitPrice' => -500,
                    'grossTotalAmount' => -500,
                    'netTotalAmount' => -500,
                ],
                [
                    'quantity' => 1,
                    'unitPrice' => 249,
                    'grossTotalAmount' => 299,
                    'netTotalAmount' => 249,
                    'taxAmount' => 50,
                    'reference' => 'shipping',
                ],
            ],
        ];
        yield 'gross-null-shipping-name' => [
            CartPrice::TAX_STATE_GROSS,
            [self::createOrderItemEntity('item1', 10.0, 1, 2.0)],
            (function () {
                $deliveryEntity = self::createOrderDeliveryEntity('shipping1', 2.99, 1, 0.5);
                $deliveryEntity->getShippingMethod()->setName(null);

                return $deliveryEntity;
            })(),
            [
                [
                    'quantity' => 1,
                    'unitPrice' => 800,
                    'grossTotalAmount' => 1000,
                    'netTotalAmount' => 800,
                    'taxAmount' => 200,
                ],
                [
                    'quantity' => 1,
                    'unitPrice' => 249,
                    'grossTotalAmount' => 299,
                    'netTotalAmount' => 249,
                    'taxAmount' => 50,
                    'reference' => 'shipping',
                ],
            ],
        ];
        yield 'net-no-shipping-cost' => [
            CartPrice::TAX_STATE_NET,
            [self::createOrderItemEntity('item1', 10.0, 2, 2.0)],
            self::createOrderDeliveryEntity('shipping1', 0.0, 1, 0.0),
            [
                [
                    'quantity' => 2,
                    'unitPrice' => 500,
                    'grossTotalAmount' => 1200,
                    'netTotalAmount' => 1000,
                    'taxAmount' => 200,
                ],
            ],
        ];
        yield 'gross-uneven-tax-per-unit' => [
            CartPrice::TAX_STATE_GROSS,
            [self::createOrderItemEntity('item1', 10.0, 3, 2.0)],
            self::createOrderDeliveryEntity('shipping1', 0.0, 1, 0.0),
            [
                [
                    'quantity' => 3,
                    'unitPrice' => 266,
                    'grossTotalAmount' => 1000,
                    'netTotalAmount' => 800,
                    'taxAmount' => 200,
                ],
            ],
        ];
    }

    /**
     * @return iterable<string, array{string, list<array{string, float, int, float}>, list<array<string, int|string>>}>
     */
    public static function cartProvider(): iterable
    {
        yield 'net-single-item' => [
            CartPrice::TAX_STATE_NET,
            [
                ['foo', 9.0, 1, 2.0],
            ],
            [
                [
                    'quantity' => 1,
                    'unitPrice' => 900,
                    'grossTotalAmount' => 1100,
                    'netTotalAmount' => 900,
                    'taxAmount' => 200,
                ],
            ],
        ];

        yield 'gross-single-item' => [
            CartPrice::TAX_STATE_GROSS,
            [
                ['bar', 11.0, 1, 2.0],
            ],
            [
                [
                    'quantity' => 1,
                    'unitPrice' => 900,
                    'grossTotalAmount' => 1100,
                    'netTotalAmount' => 900,
                    'taxAmount' => 200,
                ],
            ],
        ];

        yield 'gross-multiple-items' => [
            CartPrice::TAX_STATE_GROSS,
            [
                ['item1', 10.0, 2, 1.5],
                ['item2', 5.0, 1, 1.0],
            ],
            [
                [
                    'quantity' => 2,
                    'unitPrice' => 425,
                    'grossTotalAmount' => 1000,
                    'netTotalAmount' => 850,
                    'taxAmount' => 150,
                ],
                [
                    'quantity' => 1,
                    'unitPrice' => 400,
                    'grossTotalAmount' => 500,
                    'netTotalAmount' => 400,
                    'taxAmount' => 100,
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $actual
     * @param array<string,mixed> $expected
     */
    private function assertItems(array $actual, array $expected): void
    {
        foreach ($expected as $field => $value) {
            $this->assertArrayHasKey($field, $actual);
            $this->assertSame($value, $actual[$field]);
        }
    }

    private function createLineItem(string $id, float $price, int $quantity, float $tax): LineItem
    {
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, null, $quantity);
        $lineItem->setLabel($id);
        $lineItem->setStackable(true);
        $lineItem->setPrice(self::createCalculatedPrice($price, $quantity, $tax));

        return $lineItem;
    }

    private static function createOrderItemEntity(string $id, float $price, int $quantity, float $tax): OrderLineItemEntity
    {
        $item = new OrderLineItemEntity();
        $item->setId($id);
        $item->setLabel($id);
        $item->setPosition(0);
        $item->setQuantity($quantity);
        $item->setPrice(self::createCalculatedPrice($price, $quantity, $tax));

        return $item;
    }

    private static function createOrderDeliveryEntity(string $id, float $price, int $quantity, float $tax): OrderDeliveryEntity
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId($id);

        $method = new ShippingMethodEntity();
        $method->setId($id);
        $method->setName('shipping_' . $id);

        $delivery->setShippingMethod($method);

        $delivery->setShippingCosts(self::createCalculatedPrice($price, $quantity, $tax));

        return $delivery;
    }

    private static function createCalculatedPrice(float $totalPrice, int $quantity, float $taxAmount): CalculatedPrice
    {
        $unit = $quantity > 0 ? $totalPrice / $quantity : 0.0;
        $netPortion = $taxAmount > 0 ? $totalPrice - $taxAmount : $totalPrice;
        $taxes = $taxAmount > 0
            ? new CalculatedTaxCollection([new CalculatedTax($taxAmount, $taxAmount / $netPortion, $netPortion)])
            : new CalculatedTaxCollection([]);

        return new CalculatedPrice(
            $unit,
            $totalPrice,
            $taxes,
            new TaxRuleCollection(),
            $quantity
        );
    }
}
