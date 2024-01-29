<?php

declare(strict_types=1);

namespace Nets\Checkout\Service;

use Nets\Checkout\Facade\AsyncPaymentFinalizePay;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class Checkout implements AsynchronousPaymentHandlerInterface
{
    private AsyncPaymentFinalizePay $payFinalizeFacade;

    public function __construct(AsyncPaymentFinalizePay $payFinalizeFacade)
    {
        $this->payFinalizeFacade = $payFinalizeFacade;
    }

    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
            $paymentUrl = $this->payFinalizeFacade->pay($transaction, $dataBag, $salesChannelContext);

            return new RedirectResponse((string) $paymentUrl);
    }

    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
            $this->payFinalizeFacade->finalize($transaction, $request, $salesChannelContext);
    }
}
