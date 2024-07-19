<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment;

use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentDetails\CardDetails;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentDetails\InvoiceDetails;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentDetails\PaymentTypeEnum;

readonly class PaymentDetails
{
    public function __construct(
        private ?PaymentTypeEnum $paymentType,
        private ?string $paymentMethod,
        private ?InvoiceDetails $invoiceDetails,
        private ?CardDetails $cardDetails
    ) {
    }

    public function getPaymentType(): ?PaymentTypeEnum
    {
        return $this->paymentType;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function getInvoiceDetails(): ?InvoiceDetails
    {
        return $this->invoiceDetails;
    }

    public function getCardDetails(): ?CardDetails
    {
        return $this->cardDetails;
    }
}
