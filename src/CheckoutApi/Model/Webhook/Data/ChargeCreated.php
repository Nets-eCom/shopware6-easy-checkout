<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook\Data;

use NexiNets\CheckoutApi\Model\Webhook\Data;

class ChargeCreated extends Data
{
    /**
     * @param OrderItem[] $orderItems
     */
    public function __construct(
        string $paymentId,
        private readonly string $chargeId,
        private readonly string $paymentMethod,
        private readonly string $paymentType,
        private readonly array $orderItems,
        private readonly Amount $amount,
    ) {
        parent::__construct($paymentId);
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

    /**
     * @return OrderItem[]
     */
    public function getOrderItems(): array
    {
        return $this->orderItems;
    }

    public function getAmount(): Amount
    {
        return $this->amount;
    }
}
