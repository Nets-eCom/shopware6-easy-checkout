<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment;

readonly class Charge
{
    /**
     * @param array<Item> $orderItems
     */
    public function __construct(
        private string $chargeId,
        private int $amount,
        private \DateTimeInterface $created,
        private array $orderItems
    ) {
    }

    public function getChargeId(): string
    {
        return $this->chargeId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCreated(): \DateTimeInterface
    {
        return $this->created;
    }

    /**
     * @return array<Item>
     */
    public function getOrderItems(): array
    {
        return $this->orderItems;
    }
}
