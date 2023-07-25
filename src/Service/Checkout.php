<?php
declare(strict_types = 1);
namespace Nets\Checkout\Service;

use Nets\Checkout\Service\Easy\CheckoutService;
use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiExceptionHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Psr\Container\ContainerInterface;
use Nets\Checkout\Facade\AsyncPaymentFinalizePay;
/**
 * Description of NetsCheckout
 *
 */
class Checkout implements AsynchronousPaymentHandlerInterface
{
    private ContainerInterface $container;


     /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    
    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
		$this->loadFacade();
        try {

            $paymentUrl = $this->payFinalizeFacade->pay($transaction,$dataBag, $salesChannelContext);
			return new RedirectResponse((string)$paymentUrl);
        } catch (AsyncPaymentFinalizeException|CustomerCanceledAsyncPaymentException $ex) {

           

        } 
      
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param Request $request
     * @param SalesChannelContext $salesChannelContext
     */
    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
		$this->loadFacade();
        try {

            $this->payFinalizeFacade->finalize($transaction,$request, $salesChannelContext);

        } catch (AsyncPaymentFinalizeException|CustomerCanceledAsyncPaymentException $ex) {

           

        } 
    }

	private function loadFacade(): void
    {
        $container = $this->container;
        $this->payFinalizeFacade = $container->get('Nets\Checkout\Facade\AsyncPaymentFinalizePay'); 
    }


}
