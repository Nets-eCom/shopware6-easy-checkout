<?php

declare(strict_types=1);

namespace NexiNets\Administration\Model;

use Symfony\Component\Validator\Constraints as Assert;

class RefundData
{
    /**
     * @param array<Item> $items
     */
    public function __construct(
        #[Assert\GreaterThanOrEqual(0.01)]
        private readonly float $amount,
        private readonly array $items = []
    ) {
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return Item[]
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
