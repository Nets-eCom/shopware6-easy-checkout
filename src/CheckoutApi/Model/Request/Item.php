<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request;

/**
 * @phpstan-type RequestItemSerialized array{
 *     name: string,
 *     quantity: int|float,
 *     unit: string,
 *     unitPrice: int,
 *     grossTotalAmount: int,
 *     netTotalAmount: int,
 *     reference: string,
 *     taxRate?: ?int,
 *     taxAmount?: ?int
 * }
 */
final readonly class Item implements \JsonSerializable
{
    public function __construct(
        private string $name,
        private float|int $quantity,
        private string $unit,
        private int $unitPrice,
        private int $grossTotalAmount,
        private int $netTotalAmount,
        private string $reference,
        private ?int $taxRate = null,
        private ?int $taxAmount = null,
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

    /**
     * @phpstan-return RequestItemSerialized
     */
    public function jsonSerialize(): array
    {
        $result = [
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unitPrice' => $this->unitPrice,
            'grossTotalAmount' => $this->grossTotalAmount,
            'netTotalAmount' => $this->netTotalAmount,
            'reference' => $this->reference,
        ];

        if ($this->taxRate !== null) {
            $result['taxRate'] = $this->taxRate;
        }

        if ($this->taxAmount !== null) {
            $result['taxAmount'] = $this->taxAmount;
        }

        return $result;
    }
}
