<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder;

use NexiNets\Administration\Model\ChargeItem;
use NexiNets\CheckoutApi\Model\Request\FullRefundCharge;
use NexiNets\CheckoutApi\Model\Request\Item;
use NexiNets\CheckoutApi\Model\Request\PartialRefundCharge;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\RequestBuilder\Helper\FormatHelper;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

class RefundRequest
{
    public function __construct(private readonly FormatHelper $helper)
    {
    }

    public function build(OrderTransactionEntity $transaction): FullRefundCharge
    {
        return new FullRefundCharge(
            $this->helper->priceToInt(
                $transaction->getAmount()->getTotalPrice()
            )
        );
    }

    /**
     * @param array{amount: float, items: array<ChargeItem>} $chargeData
     */
    public function buildPartialRefund(OrderTransactionEntity $transaction, array $chargeData): PartialRefundCharge
    {
        $itemsToRefund = [];

        foreach ($chargeData['items'] as $chargeItem) {
            $orderItem = $this->findOrderItemByReference(
                $transaction->getCustomFieldsValue(OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_ORDER),
                $chargeItem->getReference()
            );

            $quantity = (int) $chargeItem->getQuantity();
            $unitPrice = $orderItem !== [] ? $orderItem['unitPrice'] : $this->helper->priceToInt($chargeItem->getUnitPrice());

            $grossTotalAmount = $this->helper->priceToInt($chargeItem->getGrossTotalAmount());
            $netTotalAmount = $unitPrice * $quantity;
            $taxAmount = $grossTotalAmount - $netTotalAmount;

            $itemsToRefund[] = new Item(
                $orderItem !== [] ? $orderItem['name'] : $chargeItem->getName(),
                $quantity,
                'pcs',
                $unitPrice,
                $grossTotalAmount,
                $netTotalAmount,
                $orderItem !== [] ? $orderItem['reference'] : $chargeItem->getReference(),
                $orderItem !== [] ? $orderItem['taxRate'] : $chargeItem->getTaxRate() ?? null,
                $taxAmount > 0 ? $taxAmount : null
            );
        }

        return new PartialRefundCharge($itemsToRefund);
    }

    /**
     * @param array<string, array<string, mixed>> $order
     *
     * @return array<string, mixed>
     */
    private function findOrderItemByReference(array $order, string $reference): array
    {
        foreach ($order['items'] as $item) {
            if ($item['reference'] === $reference) {
                return $item;
            }
        }

        return [];
    }
}
