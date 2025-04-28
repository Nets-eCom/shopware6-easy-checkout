<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use Nexi\Checkout\RequestBuilder\Helper\FormatHelper;
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
            $unitPrice = $this->getUnitPrice($lineItem->getPrice(), $lineItem->getQuantity(), $order->getTaxStatus());
            $taxRate = $this->getTaxRate($lineItem);
            $grossTotalAmount = $this->priceToInt($lineItem->getPrice()->getTotalPrice());
            $netTotalAmount = $unitPrice * $lineItem->getQuantity();
            $reference = $this->getReference($lineItem);
            $taxAmount = $this->getTaxAmount($grossTotalAmount, $netTotalAmount);

            $items[] = new Item(
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

        if ($order->getShippingTotal() > 0) {
            $shippingCost = $order->getShippingCosts();
            $shippingUnitPrice = $shippingCost->getUnitPrice();
            $shippingQuantity = $shippingCost->getQuantity();
            $shippingNetTotalAmount = $this->priceToInt($shippingUnitPrice * $shippingQuantity);
            $shippingGrossTotalAmount = $this->priceToInt($shippingCost->getTotalPrice());

            $nexiItems[] = new Item(
                $order->getDeliveries()->getShippingMethods()->first()->getName(),
                $shippingQuantity,
                'pcs',
                $this->priceToInt($shippingUnitPrice),
                $shippingGrossTotalAmount,
                $shippingNetTotalAmount,
                substr('shipping', 0, 128),
                $this->getShippingTaxRate($order),
                $shippingGrossTotalAmount - $shippingNetTotalAmount
            );
        }

        return $nexiItems;
    }

    private function getShippingTaxRate(OrderEntity $order): int
    {
        $shippingCost = $order->getShippingCosts();

        $taxRate = $shippingCost->getCalculatedTaxes()->first();

        return empty($taxRate) ? 0 : (int) round($taxRate->getTaxRate() * 100);
    }

    /**
     * @return list<Item>
     */
    public function createFromCart(Cart $cart): array
    {
        $items = [];

        foreach ($cart->getLineItems() as $lineItem) {
            $items[] = $this->createFromLineItem(
                $lineItem,
                $this->getUnitPrice($lineItem->getPrice(), $lineItem->getQuantity(), $cart->getPrice()->getTaxStatus())
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

    private function getTaxRate(OrderLineItemEntity $lineItem): int
    {
        $taxRate = $lineItem->getPrice()->getCalculatedTaxes()->first();

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

    private function createFromLineItem(
        LineItem $lineItem,
        int $unitPrice
    ): Item {
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
}
