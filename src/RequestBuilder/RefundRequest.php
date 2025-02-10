<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder;

use Nexi\Checkout\Administration\Model\ChargeItem;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\RequestBuilder\Helper\FormatHelper;
use NexiCheckout\Model\Request\FullRefundCharge;
use NexiCheckout\Model\Request\Item;
use NexiCheckout\Model\Request\PartialRefundCharge;
use NexiCheckout\Model\Result\RetrievePayment\Charge;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

class RefundRequest
{
    public function __construct(private readonly FormatHelper $helper)
    {
    }

    public function buildFullRefund(Charge $charge): FullRefundCharge
    {
        return new FullRefundCharge(
            $charge->getAmount(),
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
                $transaction->getCustomFieldsValue(OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_ORDER),
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

    public function buildUnrelatedPartialRefund(int $refundAmount): PartialRefundCharge
    {
        $reference = \sprintf('refund %d', $refundAmount);

        return new PartialRefundCharge([new Item(
            $reference,
            1,
            'pcs',
            $refundAmount,
            $refundAmount,
            $refundAmount,
            substr($reference, 0, 128),
        )]);
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
