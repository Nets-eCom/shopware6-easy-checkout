<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentDetails;

readonly class CardDetails
{
    public function __construct(private ?string $maskedPan, private ?string $expiryDate)
    {
    }

    public function getMaskedPan(): ?string
    {
        return $this->maskedPan;
    }

    public function getExpiryDate(): ?string
    {
        return $this->expiryDate;
    }
}
