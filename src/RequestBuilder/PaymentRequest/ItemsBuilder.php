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
     * @return list<Item>
     */
    public function createFromOrder(OrderEntity $order): array
    {
        $items = [];

        $taxStatus = $order->getTaxStatus() ?? $order->getPrice()->getTaxStatus();

        foreach ($order->getNestedLineItems() as $lineItem) {
            $items[] = $this->createFromOrderLineItem(
                $lineItem,
                $taxStatus
            );
        }

        $shippingCosts = $order->getShippingCosts();

        if ($shippingCosts->getTotalPrice() > 0) {
            $items[] = $this->createShippingItem(
                $order->getDeliveries()->getShippingMethods()->first()->getName() ?? 'Shipping',
                $shippingCosts,
                $taxStatus
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

        $taxStatus = $cart->getPrice()->getTaxStatus();

        foreach ($cart->getLineItems() as $lineItem) {
            $items[] = $this->createFromCartLineItem(
                $lineItem,
                $taxStatus
            );
        }

        $shippingCosts = $cart->getShippingCosts();
        if ($shippingCosts->getTotalPrice() > 0) {
            $items[] = $this->createShippingItem(
                $cart->getDeliveries()->first()->getShippingMethod()->getName() ?? 'Shipping',
                $shippingCosts,
                $taxStatus
            );
        }

        return $items;
    }

    private function createFromOrderLineItem(
        OrderLineItemEntity $lineItem,
        string $taxStatus
    ): Item {
        $itemPrice = $lineItem->getPrice();
        $unitPrice = $this->getUnitPrice($itemPrice, $lineItem->getQuantity(), $taxStatus);
        $calculatedTaxes = $itemPrice->getCalculatedTaxes();
        $taxAmount = $calculatedTaxes->getAmount();
        $grossTotalAmount = $this->priceToInt(
            $taxStatus !== CartPrice::TAX_STATE_GROSS
                ? $itemPrice->getTotalPrice() + $taxAmount
                : $itemPrice->getTotalPrice()
        );
        $netTotalAmount = $this->priceToInt(
            $taxStatus !== CartPrice::TAX_STATE_GROSS
                ? $itemPrice->getTotalPrice()
                : $itemPrice->getTotalPrice() - $taxAmount
        );
        $reference = $lineItem->getPayload()['productNumber'] ?? $lineItem->getProductId() ?? $lineItem->getId();

        return new Item(
            $this->sanitize($lineItem->getLabel()),
            $lineItem->getQuantity(),
            self::ITEM_UNIT,
            $unitPrice,
            $grossTotalAmount,
            $netTotalAmount,
            substr($reference, 0, 128),
            $this->getTaxRate($itemPrice),
            $this->priceToInt($taxAmount)
        );
    }

    private function createFromCartLineItem(
        LineItem $lineItem,
        string $taxStatus
    ): Item {
        $itemPrice = $lineItem->getPrice();
        $itemTotalPrice = $itemPrice->getTotalPrice();
        $calculatedTaxAmount = $itemPrice->getCalculatedTaxes()->getAmount();
        $quantity = $lineItem->getQuantity();
        $unitPrice = $this->getUnitPrice($itemPrice, $quantity, $taxStatus);
        $reference = $lineItem->getPayload()['productNumber'] ?? $lineItem->getReferencedId() ?? $lineItem->getId();

        $grossTotalAmount = $this->priceToInt(
            $taxStatus !== CartPrice::TAX_STATE_GROSS
                ? $itemTotalPrice + $calculatedTaxAmount
                : $itemTotalPrice
        );
        $netTotalAmount = $this->priceToInt(
            $taxStatus !== CartPrice::TAX_STATE_GROSS
                ? $itemTotalPrice
                : $itemTotalPrice - $calculatedTaxAmount
        );

        return new Item(
            $this->sanitize($lineItem->getLabel()),
            $quantity,
            self::ITEM_UNIT,
            $unitPrice,
            $grossTotalAmount,
            $netTotalAmount,
            substr($reference, 0, 128),
            $this->getTaxRate($itemPrice),
            $this->priceToInt($calculatedTaxAmount)
        );
    }

    private function createShippingItem(
        string $name,
        CalculatedPrice $shippingCost,
        string $taxStatus
    ): Item {
        $shippingTotal = $this->priceToInt($shippingCost->getTotalPrice());
        $shippingQuantity = $shippingCost->getQuantity();
        $shippingUnitPrice = $this->getUnitPrice($shippingCost, $shippingQuantity, $taxStatus);
        $taxAmount = $this->priceToInt($shippingCost->getCalculatedTaxes()->getAmount());

        $shippingGrossTotalAmount = $taxStatus !== CartPrice::TAX_STATE_GROSS
            ? $shippingTotal + $taxAmount
            : $shippingTotal;

        $shippingNetTotalAmount = $taxStatus !== CartPrice::TAX_STATE_GROSS
            ? $shippingGrossTotalAmount
            : $shippingGrossTotalAmount - $taxAmount;

        return new Item(
            $name,
            $shippingQuantity,
            self::ITEM_UNIT,
            $shippingUnitPrice,
            $shippingGrossTotalAmount,
            $shippingNetTotalAmount,
            'shipping',
            $this->getTaxRate($shippingCost),
            $taxAmount
        );
    }

    private function getUnitPrice(CalculatedPrice $calculatedPrice, int $qty, string $taxStatus): int
    {
        $unitPrice = $this->priceToInt($calculatedPrice->getUnitPrice());

        if ($taxStatus !== CartPrice::TAX_STATE_GROSS) {
            return $unitPrice;
        }

        $taxGross = $this->priceToInt($calculatedPrice->getCalculatedTaxes()->getAmount());

        if ($taxGross === 0) {
            return $unitPrice;
        }

        $taxPerUnit = (int) round($taxGross / $qty);

        return $unitPrice - $taxPerUnit;
    }

    private function getTaxRate(CalculatedPrice $calculatedPrice): int
    {
        $taxRate = $calculatedPrice->getCalculatedTaxes()->first();

        return empty($taxRate) ? 0 : (int) round($taxRate->getTaxRate() * 100);
    }

    private function priceToInt(float $price): int
    {
        return $this->helper->priceToInt($price);
    }

    private function sanitize(string $label): string
    {
        return $this->helper->sanitizeString($label);
    }
}
