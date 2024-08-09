<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook\Data;

class OrderItem
{
    public function __construct(
        private readonly string $reference,
        private readonly string $name,
        private readonly int $quantity,
        private readonly string $unit,
        private readonly int $unitPrice,
        private readonly int $taxRate,
        private readonly int $taxAmount,
        private readonly int $netTotalAmount,
        private readonly int $grossTotalAmount,
    ) {
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getQuantity(): int
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

    public function getTaxRate(): int
    {
        return $this->taxRate;
    }

    public function getTaxAmount(): int
    {
        return $this->taxAmount;
    }

    public function getNetTotalAmount(): int
    {
        return $this->netTotalAmount;
    }

    public function getGrossTotalAmount(): int
    {
        return $this->grossTotalAmount;
    }
}
