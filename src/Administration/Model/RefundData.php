<?php

declare(strict_types=1);

namespace NexiNets\Administration\Model;

use Symfony\Component\Validator\Constraints as Assert;

class RefundData
{
    /**
     * @param array<string, array{amount: float, items: array<ChargeItem>}> $charges
     */
    public function __construct(
        #[Assert\GreaterThanOrEqual(0.01)]
        private readonly float $amount,
        #[Assert\Valid]
        private readonly array $charges = [],
    ) {
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return array<string, array{amount: float, items: array<ChargeItem>}>
     */
    public function getCharges(): array
    {
        return $this->charges;
    }
}
