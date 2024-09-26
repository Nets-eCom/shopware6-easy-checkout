<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook\Data;

use NexiNets\CheckoutApi\Model\Webhook\Shared\Data;

class CancelCreatedData extends Data
{
    /**
     * @param OrderItem[] $orderItems
     */
    public function __construct(
        string $paymentId,
        private readonly string $cancelId,
        private readonly array $orderItems,
        private readonly Amount $amount,
    ) {
        parent::__construct($paymentId);
    }

    public function getCancelId(): string
    {
        return $this->cancelId;
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
