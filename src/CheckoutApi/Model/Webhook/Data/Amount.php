<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook\Data;

class Amount
{
    public function __construct(
        private readonly int $amount,
        private readonly string $currency,
    ) {
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }
}
