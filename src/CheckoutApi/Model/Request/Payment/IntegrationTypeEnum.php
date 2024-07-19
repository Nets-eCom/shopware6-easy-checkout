<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\Payment;

enum IntegrationTypeEnum
{
    case EmbeddedCheckout;
    case HostedPaymentPage;
}
