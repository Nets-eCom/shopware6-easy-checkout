<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment;

class Payment
{
    private readonly PaymentStatusEnum $status;

    /**
     * @param list<Refund>|null $refunds
     * @param list<Charge>|null $charges
     */
    public function __construct(
        private readonly string $paymentId,
        private readonly OrderDetails $orderDetails,
        private readonly Checkout $checkout,
        private readonly \DateTimeInterface $created,
        private readonly Consumer $consumer,
        private readonly ?\DateTimeInterface $terminated = null,
        private readonly ?Summary $summary = null,
        private readonly ?PaymentDetails $paymentDetails = null,
        private readonly ?array $refunds = null,
        private readonly ?array $charges = null,
        private readonly ?string $myReference = null,
    ) {
        $this->status = $this->specifyStatus();
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getSummary(): Summary
    {
        if (!$this->summary instanceof Summary) {
            throw new \DomainException('Summary doesn\'t exist');
        }

        return $this->summary;
    }

    public function getConsumer(): ?Consumer
    {
        return $this->consumer;
    }

    public function getPaymentDetails(): ?PaymentDetails
    {
        return $this->paymentDetails;
    }

    public function getOrderDetails(): OrderDetails
    {
        return $this->orderDetails;
    }

    public function getCheckout(): Checkout
    {
        return $this->checkout;
    }

    public function getCreated(): \DateTimeInterface
    {
        return $this->created;
    }

    /**
     * @return list<Refund>|null
     */
    public function getRefunds(): ?array
    {
        return $this->refunds;
    }

    /**
     * @return list<Charge>|null
     */
    public function getCharges(): ?array
    {
        return $this->charges;
    }

    public function getTerminated(): ?\DateTimeInterface
    {
        return $this->terminated;
    }

    public function getMyReference(): ?string
    {
        return $this->myReference;
    }

    public function getStatus(): PaymentStatusEnum
    {
        return $this->status;
    }

    private function specifyStatus(): PaymentStatusEnum
    {
        return match (true) {
            $this->isCancelled() => PaymentStatusEnum::CANCELLED,
            $this->isFullyRefunded() => PaymentStatusEnum::REFUNDED,
            $this->isPendingRefund() => PaymentStatusEnum::PENDING_REFUND,
            $this->isRefunded() => PaymentStatusEnum::PARTIALLY_REFUNDED,
            $this->isFullyCharged() => PaymentStatusEnum::CHARGED,
            $this->isCharged() => PaymentStatusEnum::PARTIALLY_CHARGED,
            $this->isReserved() => PaymentStatusEnum::RESERVED,
            $this->terminated instanceof \DateTimeInterface => PaymentStatusEnum::TERMINATED,
            default => PaymentStatusEnum::NEW,
        };
    }

    private function isReserved(): bool
    {
        return $this->getSummary()->getReservedAmount() > 0;
    }

    private function isFullyCharged(): bool
    {
        $summary = $this->getSummary();

        if ($this->isRefunded() || !$this->isCharged()) {
            return false;
        }

        return $summary->getChargedAmount() === $summary->getReservedAmount();
    }

    private function isCharged(): bool
    {
        return $this->getSummary()->getChargedAmount() > 0;
    }

    private function isFullyRefunded(): bool
    {
        $summary = $this->getSummary();

        if ($summary->getRefundedAmount() === 0) {
            return false;
        }

        // @todo check if reserved amount can be 0 (methods with auto-charge by design)
        return $summary->getRefundedAmount() === $summary->getReservedAmount();
    }

    private function isRefunded(): bool
    {
        return $this->getSummary()->getRefundedAmount() > 0;
    }

    private function isPendingRefund(): bool
    {
        $refunds = $this->getRefunds();

        if ($refunds === null || $refunds === []) {
            return false;
        }

        return array_filter(
            $refunds,
            fn (Refund $refund) => $refund->getRefundState() === RefundStateEnum::PENDING
        ) !== [];
    }

    private function isCancelled(): bool
    {
        return $this->getSummary()->getCancelledAmount() > 0;
    }
}
