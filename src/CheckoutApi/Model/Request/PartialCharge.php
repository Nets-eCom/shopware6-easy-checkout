<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request;

use NexiNets\CheckoutApi\Model\Request\Charge\Shipping;

class PartialCharge extends Charge
{
    /**
     * @param array<Item> $orderItems
     */
    public function __construct(
        protected readonly array $orderItems,
        protected bool $finalCharge = true,
        protected ?Shipping $shipping = null,
        protected ?string $myReference = null,
        protected ?string $paymentMethodReference = null
    ) {
        if ($orderItems === []) {
            throw new \LogicException('Order items cannot be empty');
        }

        parent::__construct($finalCharge, $shipping, $myReference, $paymentMethodReference);
    }

    public function getAmount(): int
    {
        return array_reduce(
            $this->orderItems,
            fn (int $carry, Item $item): int => $carry + $item->getGrossTotalAmount(),
        );
    }

    /**
     * @return array{
     *     amount: int,
     *     orderItems: array<Item>,
     *     shipping: ?Shipping,
     *     myReference: ?string,
     *     paymentMethodReference: ?string
     * }
     */
    public function jsonSerialize(): array
    {
        $result = parent::jsonSerialize();

        $result['orderItems'] = $this->orderItems;

        return $result;
    }
}
