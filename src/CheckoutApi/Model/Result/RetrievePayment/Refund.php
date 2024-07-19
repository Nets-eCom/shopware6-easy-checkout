<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment;

class Refund
{
    /**
     * @param array<Item> $orderItems
     */
    public function __construct(
        private readonly string $refundId,
        private readonly int $amount,
        private readonly RefundStateEnum $refundState,
        private readonly \DateTimeInterface $lastUpdated,
        private readonly array $orderItems
    ) {
    }

    public function getRefundId(): string
    {
        return $this->refundId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getRefundState(): RefundStateEnum
    {
        return $this->refundState;
    }

    public function getLastUpdated(): \DateTimeInterface
    {
        return $this->lastUpdated;
    }

    /**
     * @return array<Item>
     */
    public function getOrderItems(): array
    {
        return $this->orderItems;
    }
}
