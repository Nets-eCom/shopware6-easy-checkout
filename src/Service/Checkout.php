<?php

declare(strict_types=1);

namespace Nets\Checkout\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use \Nets\Checkout\Service\Easy\CheckoutService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use \Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use \Nets\Checkout\Service\Easy\Api\Exception\EasyApiExceptionHandler;
use \Nets\Checkout\Service\Easy\Api\EasyApiService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Checkout\Cart\CartPersister;
use Symfony\Component\HttpFoundation\Session\Session;
use Shopware\Storefront\Framework\Routing\Router;

/**
 * Description of NetsCheckout
 *
 * @author mabe
 */
class Checkout implements AsynchronousPaymentHandlerInterface {

    /**
     * @var CheckoutService
     */
    private $checkout;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var EasyApiExceptionHandler
     */
    private $easyApiExceptionHandler;

    /**
     * @var EasyApiService
     */
    private $easyApiService;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepo;

    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;

    /**
     * @var ConfigService
     */
    public $configService;

    private $cartPersister;

    private $session;

    private $router;

    /**
     * Checkout constructor.
     * @param CheckoutService $checkout
     * @param SystemConfigService $systemConfigService
     * @param EasyApiExceptionHandler $easyApiExceptionHandler
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param EasyApiService $easyApiService
     * @param EntityRepositoryInterface $orderTransactionRepo
     * @param \Nets\Checkout\Service\ConfigService $configService
     * @param CartPersister $cartPersister
     */
    public function __construct(CheckoutService $checkout,
                                SystemConfigService $systemConfigService,
                                EasyApiExceptionHandler $easyApiExceptionHandler,
                                OrderTransactionStateHandler $transactionStateHandler,
                                EasyApiService $easyApiService,
                                EntityRepositoryInterface $orderTransactionRepo,
                                ConfigService $configService, CartPersister $cartPersister,
                                Session $session, Router $router)     {
        $this->systemConfigService = $systemConfigService;
        $this->checkout = $checkout;
        $this->easyApiExceptionHandler = $easyApiExceptionHandler;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->easyApiService = $easyApiService;
        $this->orderTransactionRepo = $orderTransactionRepo;
        $this->configService = $configService;
        $this->cartPersister = $cartPersister;
        $this->session = $session;
        $this->router = $router;

    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param Request $request
     * @param SalesChannelContext $salesChannelContext
     */
    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void {
        $transactionId = $transaction->getOrderTransaction()->getId();
        $environment = $this->configService->getEnvironment($salesChannelContext->getSalesChannel()->getId());
        $secretKey = $this->configService->getSecretKey($salesChannelContext->getSalesChannel()->getId());
        try {

            $this->easyApiService->setEnv($environment);

            $this->easyApiService->setAuthorizationKey($secretKey);

            $paymentId = $this->extractPaymentId();

            // it is incorrect check for captured amount
            $payment = $this->easyApiService->getPayment($paymentId);

            $transactionId = $transaction->getOrderTransaction()->getId();

            $context = $salesChannelContext->getContext();

            $this->orderTransactionRepo->update([[
                'id' => $transactionId,
                'customFields' => [
                    'nets_easy_payment_details' =>
                        ['transaction_id' => $paymentId,
                          'can_capture' => true],
                ],
            ]], $context);

            if (empty($payment->getReservedAmount())) {
                throw new CustomerCanceledAsyncPaymentException(
                    $transactionId,
                    'Customer canceled the payment on the Easy payment page'
                );
            }

        }catch (EasyApiException $ex) {
            throw new CustomerCanceledAsyncPaymentException(
                $transactionId,
                'Exception during transaction completion'
            );
        }
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @return RedirectResponse
     * @throws \Exception
     */
    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse {

        $checkoutType = $this->configService->getCheckoutType($salesChannelContext->getSalesChannel()->getId());

        if($this->checkout::CHECKOUT_TYPE_EMBEDDED == $checkoutType) {

            $paymentId = $this->extractPaymentId();
            $redirectUrl = $transaction->getReturnUrl() . '&paymentId=' . $paymentId;
            return new RedirectResponse($redirectUrl);
        }

        try {

            $result = $this->checkout->createPayment($salesChannelContext, $this->checkout::CHECKOUT_TYPE_HOSTED, $transaction);
            $PaymentCreateResult = json_decode($result, true);

        } catch(EasyApiException $ex) {

            $this->easyApiExceptionHandler->handle($ex);

            return new RedirectResponse($this->router->generate('frontend.checkout.cart.page'));
        }

        $language = $this->configService->getLanguage($salesChannelContext->getSalesChannel()->getId());
        return new RedirectResponse($PaymentCreateResult['hostedPaymentPageUrl']  . '&language='  .  $language );
    }

    // TODO change $_REQUEST[] to request_stack service
    private function extractPaymentId() {

        if(isset($_REQUEST['paymentId']) ) {
           return $_REQUEST['paymentId'];
        }

        if(isset($_REQUEST['paymentid']) ) {
            return $_REQUEST['paymentid'];
        }

    }
}
