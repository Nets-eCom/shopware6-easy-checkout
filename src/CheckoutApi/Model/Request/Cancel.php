<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request;

final readonly class Cancel implements \JsonSerializable
{
    public function __construct(private int $amount)
    {
    }

    /**
     * @return array{
     *     amount: int
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
        ];
    }
}
