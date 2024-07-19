<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment;

readonly class OrderDetails
{
    public function __construct(
        private int $amount,
        private string $currency,
        private ?string $reference = null,
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

    public function getReference(): ?string
    {
        return $this->reference;
    }
}
