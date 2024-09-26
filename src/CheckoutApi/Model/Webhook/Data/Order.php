<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook\Data;

readonly class Order
{
    /**
     * @param array<OrderItem> $orderItems
     */
    public function __construct(
        private Amount $amount,
        private string $reference,
        private array $orderItems,
    ) {
    }

    public function getAmount(): Amount
    {
        return $this->amount;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * @return array<OrderItem>
     */
    public function getOrderItems(): array
    {
        return $this->orderItems;
    }
}
