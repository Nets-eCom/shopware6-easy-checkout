<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\UpdateOrder;

readonly class Shipping implements \JsonSerializable
{
    public function __construct(private bool $costSpecified)
    {
    }

    /**
     * @return array{
     *     "costSpecified": bool
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'costSpecified' => $this->costSpecified,
        ];
    }
}
