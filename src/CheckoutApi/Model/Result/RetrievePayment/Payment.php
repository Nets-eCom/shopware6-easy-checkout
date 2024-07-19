<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment;

final readonly class Payment
{
    /**
     * @param list<Refund>|null $refunds
     * @param list<Charge>|null $charges
     */
    public function __construct(
        private string $paymentId,
        private OrderDetails $orderDetails,
        private Checkout $checkout,
        private \DateTimeInterface $created,
        private Consumer $consumer,
        private ?\DateTimeInterface $terminated = null,
        private ?Summary $summary = null,
        private ?PaymentDetails $paymentDetails = null,
        private ?array $refunds = null,
        private ?array $charges = null,
        private ?string $myReference = null,
    ) {
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getSummary(): ?Summary
    {
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
}
