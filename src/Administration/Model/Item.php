<?php

declare(strict_types=1);

namespace NexiNets\Administration\Model;

use Symfony\Component\Validator\Constraints as Assert;

class Item
{
    public function __construct(
        #[Assert\NotBlank]
        private readonly string $reference,
        #[Assert\Positive]
        private readonly int $quantity,
        #[Assert\GreaterThanOrEqual(0.01)]
        private readonly float $amount
    ) {
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
