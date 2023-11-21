<?php

declare(strict_types=1);

namespace Nets\Checkout\Service;

use Nets\Checkout\Facade\AsyncPaymentFinalizePay;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
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
        try {
            $paymentUrl = $this->payFinalizeFacade->pay($transaction, $dataBag, $salesChannelContext);

            return new RedirectResponse((string) $paymentUrl);
        } catch (AsyncPaymentFinalizeException|CustomerCanceledAsyncPaymentException $ex) {
        }
    }

    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        try {
            $this->payFinalizeFacade->finalize($transaction, $request, $salesChannelContext);
        } catch (AsyncPaymentFinalizeException|CustomerCanceledAsyncPaymentException $ex) {
        }
    }
}
