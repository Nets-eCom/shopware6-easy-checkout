<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\ChargeResult;

class Invoice
{
    public function __construct(private readonly string $invoiceNumber)
    {
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }
}
