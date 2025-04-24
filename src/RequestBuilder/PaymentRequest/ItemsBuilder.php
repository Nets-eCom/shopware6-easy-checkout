<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use Nexi\Checkout\RequestBuilder\Helper\FormatHelper;
use NexiCheckout\Model\Request\Item;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class ItemsBuilder
{
    public function __construct(
        private readonly FormatHelper $helper
    ) {
    }

    /**
     * @return Item[]
     */
    public function create(OrderEntity $order): array
    {
        $lineItems = $order->getNestedLineItems();
        $nexiItems = [];

        foreach ($lineItems as $lineItem) {
            $unitPrice = $this->getUnitPrice($lineItem, $order->getTaxStatus());
            $taxRate = $this->getTaxRate($lineItem);
            $grossTotalAmount = $this->priceToInt($lineItem->getPrice()->getTotalPrice());
            $netTotalAmount = $unitPrice * $lineItem->getQuantity();
            $reference = $this->getReference($lineItem);
            $taxAmount = $this->getTaxAmount($grossTotalAmount, $netTotalAmount);

            $nexiItems[] = new Item(
                $this->sanitize($lineItem->getLabel()),
                $lineItem->getQuantity(),
                'pcs',
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

    private function getUnitPrice(OrderLineItemEntity $lineItem, ?string $taxStatus): int
    {
        $calculatedPrice = $lineItem->getPrice();
        $unitPrice = $this->priceToInt($calculatedPrice->getUnitPrice());
        $taxAmount = $this->priceToInt($calculatedPrice->getCalculatedTaxes()->getAmount() / $lineItem->getQuantity());

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
}
