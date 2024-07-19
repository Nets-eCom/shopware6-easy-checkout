<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment;

class Summary
{
    public function __construct(
        private readonly ?int $reservedAmount,
        private readonly ?int $chargedAmount,
        private readonly ?int $refundedAmount,
        private readonly ?int $cancelledAmount
    ) {
    }

    public function getReservedAmount(): ?int
    {
        return $this->reservedAmount;
    }

    public function getChargedAmount(): ?int
    {
        return $this->chargedAmount;
    }

    public function getRefundedAmount(): ?int
    {
        return $this->refundedAmount;
    }

    public function getCancelledAmount(): ?int
    {
        return $this->cancelledAmount;
    }
}
