<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result;

abstract class PaymentResult extends AbstractResult
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
