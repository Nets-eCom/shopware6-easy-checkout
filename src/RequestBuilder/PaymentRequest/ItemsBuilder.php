<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use Nexi\Checkout\Helper\FormatHelper;
use NexiCheckout\Model\Request\Item;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class ItemsBuilder
{
    private const ITEM_UNIT = 'pcs';

    public function __construct(
        private readonly FormatHelper $helper
    ) {
    }

    /**
     * @return Item[]
     */
    public function createFromOrder(OrderEntity $order): array
    {
        $items = [];

        foreach ($order->getNestedLineItems() as $lineItem) {
            $items[] = $this->createFromOrderLineItem(
                $lineItem,
                $order->getTaxStatus()
            );
        }

        if ($order->getShippingTotal() > 0) {
            $items[] = $this->createShippingItem(
                $order->getDeliveries()->getShippingMethods()->first()->getName() ?? 'Shipping',
                $order->getShippingCosts(),
                $order->getShippingTotal(),
                $order->getTaxStatus()
            );
        }

        return $items;
    }

    /**
     * @return list<Item>
     */
    public function createFromCart(Cart $cart): array
    {
        $items = [];

        foreach ($cart->getLineItems() as $lineItem) {
            $items[] = $this->createFromCartLineItem(
                $lineItem,
                $cart->getPrice()->getTaxStatus()
            );
        }

        if ($cart->getShippingCosts()->getTotalPrice() > 0) {
            $items[] = $this->createShippingItem(
                $cart->getDeliveries()->first()->getShippingMethod()->getName() ?? 'Shipping',
                $cart->getShippingCosts(),
                $cart->getShippingCosts()->getTotalPrice(),
                $cart->getPrice()->getTaxStatus()
            );
        }

        return $items;
    }

    private function getUnitPrice(CalculatedPrice $calculatedPrice, int $quantity, ?string $taxStatus): int
    {
        $unitPrice = $this->priceToInt($calculatedPrice->getUnitPrice());
        $taxAmount = $this->priceToInt($calculatedPrice->getCalculatedTaxes()->getAmount() / $quantity);

        return $taxStatus === CartPrice::TAX_STATE_GROSS ? $unitPrice - $taxAmount : $unitPrice;
    }

    private function getTaxRate(CalculatedPrice $calculatedPrice): int
    {
        $taxRate = $calculatedPrice->getCalculatedTaxes()->first();

        return empty($taxRate) ? 0 : (int) round($taxRate->getTaxRate() * 100);
    }

    private function getTaxAmount(int $grossTotalAmount, int $netTotalAmount): ?int
    {
        $taxAmount = $grossTotalAmount - $netTotalAmount;

        return $taxAmount > 0 ? $taxAmount : null;
    }

    private function getReference(OrderLineItemEntity $lineItem): string
    {
        return $lineItem->getPayload()['productNumber'] ?? $lineItem->getProductId() ?? $lineItem->getId();
    }

    private function priceToInt(float $price): int
    {
        return $this->helper->priceToInt($price);
    }

    private function sanitize(string $label): string
    {
        return $this->helper->sanitizeString($label);
    }

    private function createFromOrderLineItem(
        OrderLineItemEntity $lineItem,
        ?string $taxStatus
    ): Item {
        $unitPrice = $this->getUnitPrice($lineItem->getPrice(), $lineItem->getQuantity(), $taxStatus);
        $taxRate = $this->getTaxRate($lineItem->getPrice());
        $grossTotalAmount = $this->priceToInt($lineItem->getPrice()->getTotalPrice());
        $netTotalAmount = $unitPrice * $lineItem->getQuantity();
        $reference = $this->getReference($lineItem);
        $taxAmount = $this->getTaxAmount($grossTotalAmount, $netTotalAmount);

        return new Item(
            $this->sanitize($lineItem->getLabel()),
            $lineItem->getQuantity(),
            self::ITEM_UNIT,
            $unitPrice,
            $grossTotalAmount,
            $netTotalAmount,
            substr($reference, 0, 128),
            $taxRate,
            $taxAmount
        );
    }

    private function createFromCartLineItem(
        LineItem $lineItem,
        string $taxStatus
    ): Item {
        $unitPrice = $this->getUnitPrice($lineItem->getPrice(), $lineItem->getQuantity(), $taxStatus);
        $calculatedTax = $lineItem->getPrice()->getCalculatedTaxes()->first();
        $reference = $lineItem->getPayload()['productNumber'] ?? $lineItem->getReferencedId() ?? $lineItem->getId();
        $taxRate = $calculatedTax ? (int) round($calculatedTax->getTaxRate() * 100) : 0;


        $grossTotalAmount = $this->priceToInt($lineItem->getPrice()->getTotalPrice());
        $netTotalAmount = $unitPrice * $lineItem->getQuantity();
        $taxAmount = $this->getTaxAmount($grossTotalAmount, $netTotalAmount);

        return new Item(
            $this->sanitize($lineItem->getLabel()),
            $lineItem->getQuantity(),
            self::ITEM_UNIT,
            $unitPrice,
            $grossTotalAmount,
            $netTotalAmount,
            substr($reference, 0, 128),
            $taxRate,
            $taxAmount
        );
    }

    private function createShippingItem(
        string $name,
        CalculatedPrice $shippingCost,
        float $shippingTotal,
        ?string $taxStatus
    ): Item {
        $shippingQuantity = $shippingCost->getQuantity();
        $shippingUnitPrice = $this->getUnitPrice($shippingCost, $shippingQuantity, $taxStatus);
        $shippingGrossTotalAmount = $this->priceToInt($shippingTotal);
        $shippingNetTotalAmount = $shippingUnitPrice * $shippingQuantity;

        return new Item(
            $name,
            $shippingQuantity,
            self::ITEM_UNIT,
            $shippingUnitPrice,
            $shippingGrossTotalAmount,
            $shippingNetTotalAmount,
            'shipping',
            $this->getTaxRate($shippingCost),
            $this->getTaxAmount($shippingGrossTotalAmount, $shippingNetTotalAmount)
        );
    }
}
