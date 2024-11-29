<?php

declare(strict_types=1);

namespace NexiNets\Administration\Model;

use Symfony\Component\Validator\Constraints as Assert;

class RefundData
{
    /**
     * @param array{chargeId: string, array{amount: float, items: array<ChargeItem>}}|array{} $charges
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
     * @return array{chargeId: string, array{amount: float, items: array<ChargeItem>}}|array{}
     */
    public function getCharges(): array
    {
        return $this->charges;
    }
}
