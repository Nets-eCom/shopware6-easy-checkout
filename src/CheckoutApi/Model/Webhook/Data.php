<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook;

abstract class Data
{
    public function __construct(
        private readonly string $paymentId,
    ) {
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }
}
