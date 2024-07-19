<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentDetails;

readonly class InvoiceDetails
{
    public function __construct(private ?string $invoiceNumber)
    {
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }
}
