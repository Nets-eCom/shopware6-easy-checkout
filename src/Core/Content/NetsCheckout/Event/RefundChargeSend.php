<?php

declare(strict_types=1);

namespace NexiNets\Core\Content\NetsCheckout\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

final class RefundChargeSend extends PaymentUpdated
{
    public function __construct(
        OrderEntity $order,
        OrderTransactionEntity $transaction,
        private readonly float $refundAmount
    ) {
        parent::__construct($order, $transaction);
    }

    public function getRefundAmount(): float
    {
        return $this->refundAmount;
    }
}
