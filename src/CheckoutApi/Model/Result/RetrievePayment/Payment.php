<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment;

class Payment
{
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

    public function getTerminated(): \DateTimeInterface
    {
        return $this->terminated;
    }

    public function getMyReference(): ?string
    {
        return $this->myReference;
    }

    public function isFullyCharged(): bool
    {
        $summary = $this->getSummary();

        if (!$this->isCharged()) {
            return false;
        }

        return $summary->getChargedAmount() === $summary->getReservedAmount();
    }

    public function isCharged(): bool
    {
        return $this->getSummary()->getChargedAmount() > 0;
    }
}
