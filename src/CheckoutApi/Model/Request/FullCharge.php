<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request;

use NexiNets\CheckoutApi\Model\Request\Charge\Shipping;

final class FullCharge extends Charge
{
    public function __construct(
        private readonly int $amount,
        protected bool $finalCharge = true,
        protected ?Shipping $shipping = null,
        protected ?string $myReference = null,
        protected ?string $paymentMethodReference = null
    ) {
        parent::__construct($finalCharge, $shipping, $myReference, $paymentMethodReference);
    }

    public function getAmount(): int
    {
        return $this->amount;
    }
}
