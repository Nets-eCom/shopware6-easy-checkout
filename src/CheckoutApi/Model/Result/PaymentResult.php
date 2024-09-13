<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result;

abstract class PaymentResult
{
    public function __construct(
        protected string $paymentId,
    ) {
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }
}
