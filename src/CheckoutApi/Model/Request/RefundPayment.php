<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request;

class RefundPayment implements \JsonSerializable
{
    /**
     * @param array<Item> $orderItems
     */
    public function __construct(
        private readonly int $amount,
        private readonly array $orderItems = [],
        private readonly ?string $myReference = null
    ) {
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * @return Item[]
     */
    public function getOrderItems(): array
    {
        return $this->orderItems;
    }

    public function getMyReference(): ?string
    {
        return $this->myReference;
    }

    public function jsonSerialize(): mixed
    {
        $result = [
            'amount' => $this->amount,
        ];


        if ($this->myReference !== null) {
            $result['myReference'] = $this->myReference;
        }

        if ($this->orderItems !== []) {
            $result['orderItems'] = $this->myReference;
        }


        return $result;
    }
}
