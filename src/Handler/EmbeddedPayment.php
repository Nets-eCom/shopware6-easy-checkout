<?php

declare(strict_types=1);

namespace Nexi\Checkout\Handler;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class EmbeddedPayment extends AbstractPaymentHandler
{
    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return $type !== PaymentHandlerType::RECURRING;
    }

    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct
    ): ?RedirectResponse {
        return null;
    }
}
