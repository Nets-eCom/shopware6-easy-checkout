<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook\Data;

class InvoiceDetails
{
    public function __construct(
        private readonly string $distributionType,
        private readonly string $invoiceDueDate, // @todo: Change to \DateTimeInterface
        private readonly string $invoiceNumber
    ) {
    }

    public function getDistributionType(): string
    {
        return $this->distributionType;
    }

    public function getInvoiceDueDate(): string
    {
        return $this->invoiceDueDate;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }
}
