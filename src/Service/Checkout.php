<?php

declare(strict_types=1);

namespace Nets\Checkout\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Nets\Checkout\Service\ConfigService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use \Nets\Checkout\Service\Easy\CheckoutService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use \Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use \Nets\Checkout\Service\Easy\Api\Exception\EasyApiExceptionHandler;
use \Nets\Checkout\Service\Easy\Api\EasyApiService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;


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

    /**
     * Checkout constructor.
     * @param CheckoutService $checkout
     * @param SystemConfigService $systemConfigService
     * @param EasyApiExceptionHandler $easyApiExceptionHandler
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param EasyApiService $easyApiService
     * @param EntityRepositoryInterface $orderTransactionRepo
     * @param \Nets\Checkout\Service\ConfigService $configService
     */
    public function __construct(CheckoutService $checkout,
                                SystemConfigService $systemConfigService,
                                EasyApiExceptionHandler $easyApiExceptionHandler,
                                OrderTransactionStateHandler $transactionStateHandler,
                                EasyApiService $easyApiService,
                                EntityRepositoryInterface $orderTransactionRepo,
                                ConfigService $configService)     {
        $this->systemConfigService = $systemConfigService;
        $this->checkout = $checkout;
        $this->easyApiExceptionHandler = $easyApiExceptionHandler;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->easyApiService = $easyApiService;
        $this->orderTransactionRepo = $orderTransactionRepo;
        $this->configService = $configService;
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

            // it is incorrect check for captured amount
            $payment = $this->easyApiService->getPayment($_REQUEST['paymentid']);

            $transactionId = $transaction->getOrderTransaction()->getId();
            $context = $salesChannelContext->getContext();



            $this->orderTransactionRepo->update([[
                'id' => $transactionId,
                'customFields' => [
                    'nets_easy_payment_details' =>
                        ['transaction_id' => $_REQUEST['paymentid'], 'can_capture' => true],
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
        try {
            $result = $this->checkout->createPayment($transaction, $this->systemConfigService, $salesChannelContext);
            $PaymentCreateResult = json_decode($result, true);
        } catch(EasyApiException $e) {
            $this->easyApiExceptionHandler->handle($e);
            throw new AsyncPaymentProcessException($transaction->getOrderTransaction()->getId() , $e->getMessage());
        }
        $language = $this->configService->getLanguage($salesChannelContext->getSalesChannel()->getId());
        return new RedirectResponse($PaymentCreateResult['hostedPaymentPageUrl']  . '&language='  .  $language );
    }
}
