<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder\PaymentRequest;

use NexiNets\CheckoutApi\Model\Request\Payment\Item;
use NexiNets\RequestBuilder\Helper\FormatHelper;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ItemsBuilder
{
    public function __construct(
        private readonly FormatHelper $helper
    ) {
    }

    /**
     * @return Item[]
     */
    public function create(
        OrderEntity $order,
        SalesChannelContext $salesChannelContext
    ): array {
        $lineItems = $order->getNestedLineItems();
        $nexiItems = [];

        foreach ($lineItems as $lineItem) {
            $unitPrice = $this->getUnitPrice($lineItem, $order->getTaxStatus());
            $taxRate = $this->getTaxRate($lineItem);
            $grossTotalAmount = $this->priceToInt($lineItem->getPrice()->getTotalPrice());
            $netTotalAmount = $unitPrice * $lineItem->getQuantity();
            $reference = $this->getReference($lineItem);

            $nexiItems[] = new Item(
                $this->sanitize($lineItem->getLabel()),
                $lineItem->getQuantity(),
                'pcs',
                $unitPrice,
                $grossTotalAmount,
                $netTotalAmount,
                substr($reference, 0, 128),
                $taxRate,
                $grossTotalAmount - $netTotalAmount ?: null
            );
        }

        return $nexiItems;
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
