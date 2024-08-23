<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment;

class Item
{
    public function __construct(
        private readonly string $name,
        private readonly float $quantity,
        private readonly string $unit,
        private readonly int $unitPrice,
        private readonly int $grossTotalAmount,
        private readonly int $netTotalAmount,
        private readonly string $reference,
        private readonly ?int $taxRate = null,
        private readonly ?int $taxAmount = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function getUnitPrice(): int
    {
        return $this->unitPrice;
    }

    public function getGrossTotalAmount(): int
    {
        return $this->grossTotalAmount;
    }

    public function getNetTotalAmount(): int
    {
        return $this->netTotalAmount;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getTaxRate(): ?int
    {
        return $this->taxRate;
    }

    public function getTaxAmount(): ?int
    {
        return $this->taxAmount;
    }
}
