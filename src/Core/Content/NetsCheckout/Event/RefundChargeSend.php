<?php declare(strict_types=1);

namespace NexiNets\Core\Content\NetsCheckout\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Event\SalesChannelAware;

class RefundChargeSend implements SalesChannelAware
{
    public function __construct(
        private readonly OrderEntity $order,
        private readonly OrderTransactionEntity $transaction,
        private readonly float $refundAmount
    ) {
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getTransaction(): OrderTransactionEntity
    {
        return $this->transaction;
    }

    public function getRefundAmount(): float
    {
        return $this->refundAmount;
    }

    public function getSalesChannelId(): string
    {
        return $this->order->getSalesChannelId();
    }
}
