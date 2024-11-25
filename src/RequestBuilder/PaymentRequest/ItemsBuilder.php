<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder\PaymentRequest;

use NexiNets\Administration\Model\ChargeData;
use NexiNets\CheckoutApi\Model\Request\Item;
use NexiNets\CheckoutApi\Model\Request\Payment\Order;
use NexiNets\RequestBuilder\Helper\FormatHelper;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

/**
 * @phpstan-import-type RequestOrderSerialized from Order
 * @phpstan-import-type RequestItemSerialized from Item
 */
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

        return $nexiItems;
    }

    /**
     * @param RequestOrderSerialized $orderArray
     *
     * @return Item[]
     */
    public function createForCharge(ChargeData $chargeData, array $orderArray): array
    {
        $chargeItems = $chargeData->getItems();
        $returnItems = [];

        foreach ($chargeItems as $chargeItem) {
            $item = $this->findItemByReference($orderArray, $chargeItem->getReference());

            $grossTotalAmount = $this->priceToInt($chargeItem->getAmount());
            $netTotalAmount = $item['unitPrice'] * $chargeItem->getQuantity();
            $taxAmount = $this->getTaxAmount($grossTotalAmount, $netTotalAmount);

            $returnItems[] = new Item(
                $item['name'],
                $chargeItem->getQuantity(),
                'pcs',
                $item['unitPrice'],
                $grossTotalAmount,
                $netTotalAmount,
                $item['reference'],
                $item['taxRate'],
                $taxAmount
            );
        }

        return $returnItems;
    }

    public function createUnrelatedPartialChargeItem(OrderTransactionEntity $transaction, float $amount): Item
    {
        $chargeAmount = $this->priceToInt($amount);
        $reference = \sprintf('charge %d', $transaction->getCaptures()?->count() + 1);
        $name = \sprintf('order %s %s', $transaction->getOrder()->getOrderNumber(), $reference);

        return new Item(
            $this->sanitize($name),
            1,
            'pcs',
            $chargeAmount,
            $chargeAmount,
            $chargeAmount,
            substr($reference, 0, 128),
        );
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

    /**
     * @param RequestOrderSerialized $order
     *
     * @return RequestItemSerialized
     */
    private function findItemByReference(array $order, string $reference): array
    {
        foreach ($order['items'] as $item) {
            if ($item['reference'] === $reference) {
                return $item;
            }
        }

        throw new \LogicException('Item not found');
    }
}
