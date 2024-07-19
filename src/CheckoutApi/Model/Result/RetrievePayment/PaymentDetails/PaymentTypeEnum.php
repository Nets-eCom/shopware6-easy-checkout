<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentDetails;

enum PaymentTypeEnum: string
{
    case CARD = 'CARD';
    case INVOICE = 'INVOICE';
    case A2A = 'A2a';
    case INSTALLMENT = 'INSTALLMENT';
    case WALLET = 'WALLET';
    case PREPAID_INVOICE = 'PREPAID_INVOICE';
}
