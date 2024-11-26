<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder\ChargeRequest;

use NexiNets\Administration\Model\ChargeData;
use NexiNets\CheckoutApi\Model\Request\Item;
use NexiNets\CheckoutApi\Model\Request\Payment\Order;
use NexiNets\RequestBuilder\Helper\FormatHelper;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

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
                $item['taxRate'] ?? null,
                $taxAmount
            );
        }

        return $returnItems;
    }

    /**
     * @return Item[]
     */
    public function createUnrelatedPartialChargeItem(OrderTransactionEntity $transaction, float $amount): array
    {
        $chargeAmount = $this->priceToInt($amount);
        $reference = \sprintf('charge %d', $transaction->getCaptures()?->count() + 1);
        $name = \sprintf('order %s %s', $transaction->getOrder()->getOrderNumber(), $reference);

        return [new Item(
            $this->sanitize($name),
            1,
            'pcs',
            $chargeAmount,
            $chargeAmount,
            $chargeAmount,
            substr($reference, 0, 128),
        )];
    }

    private function getTaxAmount(int $grossTotalAmount, int $netTotalAmount): ?int
    {
        $taxAmount = $grossTotalAmount - $netTotalAmount;

        return $taxAmount > 0 ? $taxAmount : null;
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
