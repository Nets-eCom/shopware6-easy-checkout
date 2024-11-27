<?php

declare(strict_types=1);

namespace NexiNets\Administration\Model;

class ChargeItem
{
    public function __construct(
        #[Assert\NotBlank]
        private readonly string $chargeId,
        #[Assert\NotBlank]
        private readonly string $reference,
        #[Assert\Positive]
        private readonly int $quantity,
        #[Assert\GreaterThanOrEqual(0.01)]
        private readonly float $amount
    ) {}

    public function getChargeId(): string
    {
        return $this->chargeId;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }
}