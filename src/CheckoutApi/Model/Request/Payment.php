<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request;

use NexiNets\CheckoutApi\Model\Request\Payment\Checkout;
use NexiNets\CheckoutApi\Model\Request\Payment\Notification;
use NexiNets\CheckoutApi\Model\Request\Payment\Order;

class Payment implements \JsonSerializable
{
    public function __construct(
        private readonly Order $order,
        private readonly Checkout $checkout,
        private readonly ?Notification $notification = null,
        private readonly ?string $merchantNumber = null,
        private readonly ?string $myReference = null
    ) {
    }

    /**
     * @return array{
     *     order: Order,
     *     checkout: Checkout,
     *     notifications: ?Notification,
     *     merchantNumber: ?string,
     *     myReference:  ?string
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'order' => $this->order,
            'checkout' => $this->checkout,
            'notifications' => $this->notification,
            'merchantNumber' => $this->merchantNumber,
            'myReference' => $this->myReference,
        ];
    }
}
