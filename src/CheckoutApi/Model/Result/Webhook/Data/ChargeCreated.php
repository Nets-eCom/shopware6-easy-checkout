<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\Webhook\Data;

use NexiNets\CheckoutApi\Model\Result\Webhook\Data;

class ChargeCreated extends Data
{
    public function __construct(
        protected readonly string $paymentId,
        protected readonly string $chargeId,
        protected readonly string $paymentMethod,
        protected readonly string $paymentType,
        protected readonly array $orderItems,
        protected readonly array $amount,
    ) {
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getChargeId(): string
    {
        return $this->chargeId;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function getPaymentType(): string
    {
        return $this->paymentType;
    }
}
