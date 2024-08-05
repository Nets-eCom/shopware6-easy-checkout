<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\Webhook;

abstract class Data
{
    public function __construct(
        protected readonly string $paymentId,
    ) {
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }
}
