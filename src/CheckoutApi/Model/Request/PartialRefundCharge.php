<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request;

class PartialRefundCharge extends RefundCharge
{
    /**
     * @param array<Item> $orderItems
     */
    public function __construct(
        protected readonly array $orderItems,
        ?string $myReference = null,
    ) {
        if ($orderItems === []) {
            throw new \LogicException('Order items cannot be empty');
        }

        parent::__construct($myReference);
    }

    public function getAmount(): int
    {
        return array_reduce(
            $this->orderItems,
            fn (int $carry, Item $item): int => $carry + $item->getGrossTotalAmount(),
            0
        );
    }

    /**
     * @return array{
     *     amount: int,
     *     orderItems: array<Item>,
     *     myReference: ?string,
     * }
     */
    public function jsonSerialize(): array
    {
        $result = parent::jsonSerialize();

        $result['orderItems'] = $this->orderItems;

        return $result;
    }
}
