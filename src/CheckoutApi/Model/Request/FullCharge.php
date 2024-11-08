<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request;

use NexiNets\CheckoutApi\Model\Request\Charge\Shipping;

final class FullCharge extends Charge
{
    public function __construct(
        private readonly int $amount,
        ?Shipping $shipping = null,
        ?string $myReference = null,
        ?string $paymentMethodReference = null
    ) {
        parent::__construct(true, $shipping, $myReference, $paymentMethodReference);
    }

    public function getAmount(): int
    {
        return $this->amount;
    }
}
