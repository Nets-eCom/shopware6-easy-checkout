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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Description of NetsCheckout
 *
 * @author mabe
 */
class Checkout implements AsynchronousPaymentHandlerInterface
{

    /**
     *
     * @var CheckoutService
     */
    private $checkout;

    /**
     *
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     *
     * @var EasyApiExceptionHandler
     */
    private $easyApiExceptionHandler;

    /**
     *
     * @var EasyApiService
     */
    private $easyApiService;

    /**
     *
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepo;

    /**
     *
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;

    /**
     *
     * @var ConfigService
     */
    public $configService;

    /**
     *
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     *
     * @var Router
     */
    private $router;

    /**
     *
     * @var SessionInterface
     */
    private $session;

    private $netsApiRepository;

    private $languageRepo;

    /**
     * Checkout constructor.
     *
     * @param CheckoutService $checkout
     * @param SystemConfigService $systemConfigService
     * @param EasyApiExceptionHandler $easyApiExceptionHandler
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param EasyApiService $easyApiService
     * @param EntityRepositoryInterface $orderTransactionRepo
     * @param ConfigService $configService
     * @param EntityRepositoryInterface $orderRepository
     * @param Router $router
     * @param SessionInterface $session
     */
    public function __construct(CheckoutService $checkout, SystemConfigService $systemConfigService, EasyApiExceptionHandler $easyApiExceptionHandler, OrderTransactionStateHandler $transactionStateHandler, EasyApiService $easyApiService, EntityRepositoryInterface $orderTransactionRepo, ConfigService $configService, EntityRepositoryInterface $orderRepository, Router $router, SessionInterface $session, EntityRepositoryInterface $netsApiRepository, EntityRepositoryInterface $languageRepo)
    {
        $this->systemConfigService = $systemConfigService;
        $this->checkout = $checkout;
        $this->easyApiExceptionHandler = $easyApiExceptionHandler;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->easyApiService = $easyApiService;
        $this->orderTransactionRepo = $orderTransactionRepo;
        $this->configService = $configService;
        $this->orderRepository = $orderRepository;
        $this->router = $router;
        $this->session = $session;
        $this->netsApiRepository = $netsApiRepository;
        $this->languageRepo = $languageRepo;
    }

    /**
     *
     * @param AsyncPaymentTransactionStruct $transaction
     * @param Request $request
     * @param SalesChannelContext $salesChannelContext
     */
    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        $transactionId = $transaction->getOrderTransaction()->getId();

        $salesChannelContextId = $salesChannelContext->getSalesChannel()->getId();
        $environment = $this->configService->getEnvironment($salesChannelContextId);
        $secretKey = $this->configService->getSecretKey($salesChannelContextId);
        try {

            $this->easyApiService->setEnv($environment);
            $this->easyApiService->setAuthorizationKey($secretKey);
            $paymentId = $this->extractPaymentId();

            // it is incorrect check for captured amount
            $payment = $this->easyApiService->getPayment($paymentId);
            $transactionId = $transaction->getOrderTransaction()->getId();
            $orderId = $transaction->getOrder()->getId();
            $context = $salesChannelContext->getContext();
            $chargeNow = $this->configService->getChargeNow($salesChannelContextId);

            if ('yes' == $chargeNow) {
                $this->transactionStateHandler->paid($transaction->getOrderTransaction()
                    ->getId(), $context);
            }

            $this->orderRepository->update([
                [
                    'id' => $orderId,
                    'customFields' => [
                        'paymentId' => $paymentId
                    ]
                ]
            ], $context);

            if (empty($payment->getReservedAmount()) && empty($payment->getChargedAmount())) {
                throw new CustomerCanceledAsyncPaymentException($transactionId, 'Customer canceled the payment on the Easy payment page');
            }

            $this->orderTransactionRepo->update([
                [
                    'id' => $transactionId,
                    'customFields' => [
                        'nets_easy_payment_details' => [
                            'transaction_id' => $paymentId,
                            'can_capture' => true
                        ]
                    ]
                ]
            ], $context);

            // For inserting amount available respect to charge id
            if ($this->configService->getChargeNow($salesChannelContextId) == 'yes') {

                $this->netsApiRepository->create([
                    [
                        'order_id' => $orderId ? $orderId : '',
                        'charge_id' => $payment->getFirstChargeId() ? $payment->getFirstChargeId() : '',
                        'operation_type' => 'capture',
                        'operation_amount' => $payment->getChargedAmount() ? $payment->getChargedAmount() : '',
                        'amount_available' => $payment->getChargedAmount() ? $payment->getChargedAmount() : ''
                    ]
                ], $context);
            }
        } catch (EasyApiException $ex) {
            throw new CustomerCanceledAsyncPaymentException($transactionId, 'Exception during transaction completion');
        }
    }

    /**
     *
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @return RedirectResponse
     * @throws \Exception
     */
    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $checkoutType = $this->configService->getCheckoutType($salesChannelContext->getSalesChannel()
            ->getId());
        if ($this->checkout::CHECKOUT_TYPE_EMBEDDED == $checkoutType) {
            $paymentId = $this->extractPaymentId();
            $redirectUrl = $transaction->getReturnUrl() . '&paymentId=' . $paymentId;
            return new RedirectResponse($redirectUrl);
        }

        try {

            $result = $this->checkout->createPayment($salesChannelContext, $this->checkout::CHECKOUT_TYPE_HOSTED, $transaction);
            $PaymentCreateResult = json_decode($result, true);
            $this->session->set('nets_paymentId', $PaymentCreateResult['paymentId']);
        } catch (EasyApiException $ex) {

            $this->easyApiExceptionHandler->handle($ex);

            return new RedirectResponse($this->router->generate('frontend.checkout.cart.page'));
        }

        $langShort = $this->configService->getLang($salesChannelContext->getContext(), $this->languageRepo);

        switch ($langShort) {
            case 'de':
                $language = 'de-DE';
                break;
            case 'da':
                $language = 'da-DK';
                break;
            case 'sv':
                $language = 'sv-SE';
                break;
            case 'nb':
                $language = 'nb-NO';
                break;
            default:
                $language = 'en-GB';
        }

        return new RedirectResponse($PaymentCreateResult['hostedPaymentPageUrl'] . '&language=' . $language);
    }

    // TODO change $_REQUEST[] to request_stack service
    private function extractPaymentId()
    {
        if (isset($_REQUEST['paymentId'])) {
            return $_REQUEST['paymentId'];
        }

        if (isset($_REQUEST['paymentid'])) {
            return $_REQUEST['paymentid'];
        }
    }
}
