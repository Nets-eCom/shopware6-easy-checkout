<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook\Data;

use NexiNets\CheckoutApi\Model\Webhook\Shared\Data;

class RefundCompletedData extends Data
{
    public function __construct(
        string $paymentId,
        private readonly string $refundId,
        private readonly Amount $amount,
        private readonly ?InvoiceDetails $invoiceDetails = null,
    ) {
        parent::__construct($paymentId);
    }

    public function getRefundId(): string
    {
        return $this->refundId;
    }

    public function getAmount(): Amount
    {
        return $this->amount;
    }

    public function getInvoiceDetails(): ?InvoiceDetails
    {
        return $this->invoiceDetails;
    }
}
