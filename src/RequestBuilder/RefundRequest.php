<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder;

use NexiNets\Administration\Model\RefundData;
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
     * @param array<ChargeItem> $refundItem
     */
    public function buildPartialRefund(OrderTransactionEntity $transaction, ChargeItem $refundItem): PartialRefundCharge
    {
        $refundItems = [];

//        foreach ($refundItem as $item) {
            $orderItem = $this->findOrderItemByReference(
                $transaction->getCustomFieldsValue(OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_ORDER),
                $refundItem->getReference()
            );

            $quantity = $refundItem->getQuantity();
            $unitPrice = $orderItem['unitPrice'];

            $grossTotalAmount = $this->helper->priceToInt($refundItem->getAmount());
            $netTotalAmount = $unitPrice * $quantity;
            $taxAmount = $grossTotalAmount - $netTotalAmount;

//            $refundItems[] = new Item(
//                $orderItem['name'],
//                $quantity,
//                'pcs',
//                $unitPrice,
//                $grossTotalAmount,
//                $netTotalAmount,
//                $orderItem['reference'],
//                $orderItem['taxRate'] ?? null,
//                $taxAmount > 0 ? $taxAmount : null
//            );
////        }

        return new PartialRefundCharge([
            new Item(
                $orderItem['name'],
                $quantity,
                'pcs',
                $unitPrice,
                $grossTotalAmount,
                $netTotalAmount,
                $orderItem['reference'],
                $orderItem['taxRate'] ?? null,
                $taxAmount > 0 ? $taxAmount : null
            )
        ]);
    }

    public function buildArtificialPartialRefund(
        OrderTransactionEntity $transaction,
        float $amount
    ): PartialRefundCharge {
        $refundAmount = $this->helper->priceToInt($amount);
        $reference = \sprintf('refund %d', $transaction->getCaptures()?->count() + 1);
        $name = \sprintf('refund %s %s', $transaction->getOrder()->getOrderNumber(), $reference);

        return new PartialRefundCharge(
            [
                new Item(
                    $name,
                    1,
                    'pcs',
                    $refundAmount,
                    $refundAmount,
                    $refundAmount,
                    substr($reference, 0, 128)
                )
            ]
        );
    }

    private function findOrderItemByReference(array $order, string $reference): array
    {
        foreach ($order['items'] as $item) {
            if ($item['reference'] === $reference) {
                return $item;
            }
        }

        throw new \LogicException('Item not found');
    }
}
