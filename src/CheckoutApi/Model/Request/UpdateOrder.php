<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request;

use NexiNets\CheckoutApi\Model\Request\UpdateOrder\PaymentMethod;
use NexiNets\CheckoutApi\Model\Request\UpdateOrder\Shipping;

class UpdateOrder implements \JsonSerializable
{
    /**
     * @param list<Item> $items
     * @param list<PaymentMethod> $paymentMethods
     */
    public function __construct(
        private readonly int $amount,
        private readonly array $items,
        private readonly Shipping $shipping,
        private readonly array $paymentMethods
    ) {
    }

    /**
     * @return array{
     *     "amount": int,
     *     "items": list<Item>,
     *     "shipping": Shipping,
     *     "paymentMethods": list<PaymentMethod>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'items' => $this->items,
            'shipping' => $this->shipping,
            'paymentMethods' => $this->paymentMethods,
        ];
    }
}
