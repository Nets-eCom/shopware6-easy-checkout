<?php

declare(strict_types=1);

namespace Nets\Checkout\Service;

use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Description of NetsCheckout
 */
class Checkout implements AsynchronousPaymentHandlerInterface
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $this->loadFacade();

        try {
            $paymentUrl = $this->payFinalizeFacade->pay($transaction, $dataBag, $salesChannelContext);

            return new RedirectResponse((string) $paymentUrl);
        } catch (AsyncPaymentFinalizeException|CustomerCanceledAsyncPaymentException $ex) {
        }
    }

    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        $this->loadFacade();

        try {
            $this->payFinalizeFacade->finalize($transaction, $request, $salesChannelContext);
        } catch (AsyncPaymentFinalizeException|CustomerCanceledAsyncPaymentException $ex) {
        }
    }

    private function loadFacade(): void
    {
        $container               = $this->container;
        $this->payFinalizeFacade = $container->get('Nets\Checkout\Facade\AsyncPaymentFinalizePay');
    }
}
