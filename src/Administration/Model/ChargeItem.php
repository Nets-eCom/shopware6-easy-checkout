<?php

declare(strict_types=1);

namespace Nexi\Checkout\Administration\Model;

use Symfony\Component\Validator\Constraints as Assert;

readonly class ChargeItem
{
    public function __construct(
        #[Assert\NotBlank]
        private string $chargeId,
        #[Assert\NotBlank]
        private string $name,
        #[Assert\NotBlank]
        private float|int $quantity,
        #[Assert\NotBlank]
        private string $unit,
        #[Assert\NotBlank]
        private float $unitPrice,
        #[Assert\GreaterThanOrEqual(0.01)]
        private float $grossTotalAmount,
        #[Assert\GreaterThanOrEqual(0.01)]
        private float $netTotalAmount,
        #[Assert\NotBlank]
        private string $reference,
        private ?int $taxRate = null
    ) {
    }

    public function getChargeId(): string
    {
        return $this->chargeId;
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

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getGrossTotalAmount(): float
    {
        return $this->grossTotalAmount;
    }

    public function getNetTotalAmount(): float
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
}
