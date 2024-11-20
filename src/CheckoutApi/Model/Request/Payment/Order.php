<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\Payment;

use NexiNets\CheckoutApi\Model\Request\Item;

final class Order implements \JsonSerializable
{
    /**
     * @param list<Item> $items
     */
    public function __construct(
        private array $items,
        private readonly string $currency,
        private readonly int $amount,
        private ?string $reference = null,
    ) {
    }

    /**
     * @return list<Item>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function addItem(Item $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    public function withReference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * @return array{
     *     items: list<Item>,
     *     currency: string,
     *     amount: int,
     *     reference: ?string
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'items' => $this->items,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reference' => $this->reference,
        ];
    }
}
