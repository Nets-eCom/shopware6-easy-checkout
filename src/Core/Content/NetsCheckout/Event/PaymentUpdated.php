<?php

declare(strict_types=1);

namespace NexiNets\Core\Content\NetsCheckout\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Event\SalesChannelAware;

abstract class PaymentUpdated implements SalesChannelAware
{
    public function __construct(
        private readonly OrderEntity $order,
        private readonly OrderTransactionEntity $transaction,
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

    public function getSalesChannelId(): string
    {
        return $this->order->getSalesChannelId();
    }
}
