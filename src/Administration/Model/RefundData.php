<?php

declare(strict_types=1);

namespace NexiNets\Administration\Model;

use Symfony\Component\Validator\Constraints as Assert;

class RefundData
{
    /**
     * @param array<ChargeItem> $chargeItems
     */
    public function __construct(
        #[Assert\GreaterThanOrEqual(0.01)]
        private readonly float $amount,
        #[Assert\Valid]
        private readonly array $chargeItems = [],
    ) {
    }
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return ChargeItem[]
     */
    public function chargeItems(): array
    {
        return $this->chargeItems;
    }
}
