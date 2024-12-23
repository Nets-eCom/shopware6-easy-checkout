<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\UpdateOrder;

use NexiNets\CheckoutApi\Model\Request\Item;

class PaymentMethod implements \JsonSerializable
{
    public function __construct(private readonly string $name, private readonly Item $fee)
    {
    }

    /**
     * @return array{
     *     "name": string,
     *     "fee": Item
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'fee' => $this->fee,
        ];
    }
}
