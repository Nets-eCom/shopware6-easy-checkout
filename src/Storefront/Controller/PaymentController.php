<?php
declare(strict_types = 1);
namespace Nets\Checkout\Storefront\Controller;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\Context;
use Nets\Checkout\Service\ConfigService;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\JsonResponse;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Symfony\Component\HttpFoundation\Request;

class PaymentController extends StorefrontController
{

    private $orderRepository;

    /** @var Context $context */
    private $context;

    /**
     *
     * @var SystemConfigService
     */
    public $systemConfigService;

    private $configService;

    private $logger;

    private $checkout;

    private $easyApiService;

    private $kernel;

    private $cartService;

    private $paymentService;
    private $netsApiRepository;

    public function __construct(EntityRepositoryInterface $orderRepository, \Psr\Log\LoggerInterface $logger, \Nets\Checkout\Service\Easy\CheckoutService $checkout, SystemConfigService $systemConfigService, EasyApiService $easyApiService, \Symfony\Component\HttpKernel\KernelInterface $kernel, ConfigService $configService, CartService $cartService, PaymentService $paymentService,EntityRepositoryInterface $netsApiRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->context = Context::createDefaultContext();
        $this->logger = $logger;
        $this->checkout = $checkout;
        $this->systemConfigService = $systemConfigService;
        $this->easyApiService = $easyApiService;
        $this->kernel = $kernel;
        $this->configService = $configService;
        $this->cartService = $cartService;
        $this->paymentService = $paymentService;
        $this->netsApiRepository = $netsApiRepository;
    }

    /**
     *
     * @RouteScope(scopes={"storefront"})
     * @Route("/nets/order/finish", name="nets.finish.order.controller", options={"seo"="false"}, methods={"GET"})
     * @param Context $context
     * @param SalesChannelContext $ctx
     * @param Request $request
     * @param RequestDataBag $data
     * @return RedirectResponse|null
     * @throws EasyApiException
     */
    public function placeOrder(Context $context, SalesChannelContext $ctx, Request $request, RequestDataBag $data)
    {
        $cart = $this->cartService->getCart($ctx->getToken(), $ctx);
        $orderId = $this->cartService->order($cart, $ctx, $data);
        $orderEntity = $this->getOrderEntityById($context, $orderId);
        $salesChannelId = $ctx->getSalesChannel()->getId();
        $secretKey = $this->configService->getSecretKey($salesChannelId);
        $environment = $this->configService->getEnvironment($salesChannelId);
        $this->easyApiService->setEnv($environment);
        $this->easyApiService->setAuthorizationKey($secretKey);
        $payment = $this->easyApiService->getPayment($_REQUEST['paymentId']);
        $checkoutUrl = $payment->getCheckoutUrl();
        $refUpdate = [
            'reference' => $orderEntity->getOrderNumber(),
            'checkoutUrl' => $checkoutUrl
        ];
        $this->easyApiService->updateReference($_REQUEST['paymentId'], json_encode($refUpdate));

        //For inserting amount available respect to charge id
        if($this->configService->getChargeNow($ctx->getsalesChannel()->getId()) == 'yes'){

            $this->netsApiRepository->create([
            [
            'order_id' => $orderId?$orderId:'',
            'charge_id' => $payment->getFirstChargeId()?$payment->getFirstChargeId():'',
            'operation_type' =>'capture',
            'operation_amount' => $payment->getChargedAmount()?$payment->getChargedAmount():'',
            'amount_available' => $payment->getChargedAmount()?$payment->getChargedAmount():'',
            ]
            ], $context);
        }

        $finishUrl = $this->generateUrl('frontend.checkout.finish.page', [
            'orderId' => $orderId
        ]);
        // TODO: add Exceptions
        return $this->paymentService->handlePaymentByOrder($orderId, $data, $ctx, $finishUrl);
    }

    /**
     *
     * @RouteScope(scopes={"storefront"})
     * @Route("/nets/caheckout/validate", name="nets.test.controller.validate", options={"seo"="false"}, methods={"GET"})
     */
    public function validate(SalesChannelContext $ctx)
    {
        try {
            $secretKey = $this->configService->getSecretKey($ctx->getSalesChannel()
                ->getId());
            $environment = $this->configService->getSecretKey($ctx->getSalesChannel()
                ->getId());
            $this->easyApiService->setEnv($environment);
            $this->easyApiService->setAuthorizationKey($secretKey);
            $payment = $this->easyApiService->getPayment($_REQUEST['paymentid']);
            if (empty($payment->getReservedAmount())) {
                return $this->redirectToRoute('frontend.checkout.cart.page');
            }
        } catch (EasyApiException $ex) {
            return $this->redirectToRoute('frontend.checkout.cart.page');
        }
    }

    /**
     *
     * @RouteScope(scopes={"api"})
     * @Route("/api/nets/transaction/charge", name="nets.charge.payment.action", options={"seo"="false"}, methods={"POST"})
     * @param Context $context
     * @param Request $request
     * @return JsonResponse
     */
    public function chargePayment(Context $context, Request $request): JsonResponse
    {
        $orderId = $request->get('params')['orderId'];
        $paymentId = $request->get('params')['paymentId'];
        $amount = $request->get('params')['amount'];
        $orderEntity = $this->getOrderEntityById($context, $orderId);
        $salesChannelId = $this->getSalesChannelIdByOrderId($orderId, $context);

        try {

            $this->checkout->chargePayment($orderEntity, $salesChannelId, $context, $paymentId, $amount);
        } catch (EasyApiException $ex) {

            return new JsonResponse([
                'status' => false,
                'message' => $ex->getMessage(),
                'code' => $ex->getCode()
            ], Response::HTTP_BAD_REQUEST);
        } catch (Exception $ex) {

            return new JsonResponse([
                'status' => false,
                'message' => $ex->getMessage(),
                'code' => 0
            ], Response::HTTP_BAD_REQUEST);
        }
        return new JsonResponse([
            'status' => true
        ]);
    }

    /**
     *
     * @RouteScope(scopes={"api"})
     * @Route("/api/nets/transaction/summary", name="nets.summary.payment.action", options={"seo"="false"}, methods={"POST"})
     * @param Context $context
     * @param Request $request
     * @return JsonResponse
     * @throws EasyApiException
     */
    public function getSummaryAmounts(Context $context, Request $request)
    {
        

        $orderId = $request->get('params')['transaction']['orderId'];
        $salesChannelId = $this->getSalesChannelIdByOrderId($orderId, $context);
        $environment = $this->configService->getEnvironment($salesChannelId);
        $secretKey = $this->configService->getSecretKey($salesChannelId);
        $this->easyApiService->setEnv($environment);
        $this->easyApiService->setAuthorizationKey($secretKey);
        $orderEntity = $this->getOrderEntityById($context, $orderId);
        $transaction = $orderEntity->getTransactions()->first();
        $paymentId = $request->get('params')['transaction']['customFields']['nets_easy_payment_details']['transaction_id'];
        $payment = $this->easyApiService->getPayment($paymentId);

        
             
        
        // if($payment->getReservedAmount() > 0) {
        // $amountAvailableForCapturing = ($payment->getReservedAmount() - $payment->getChargedAmount()) / 100;
        // } else {
        // $amountAvailableForCapturing = 0;
        // }
        //
        // if($payment->getChargedAmount() - $payment->getRefundedAmount() > 0) {
        // $amountAvailableForRefunding = ($payment->getChargedAmount() - $payment->getRefundedAmount()) / 100;
        // } else {
        // $amountAvailableForRefunding = 0;
        // }
        //
        // return new JsonResponse(['amountAvailableForCapturing' => $amountAvailableForCapturing,
        // 'amountAvailableForRefunding' => $amountAvailableForRefunding,
        // 'orderState' => $transaction->getStateMachineState()->getTechnicalName()]);

        $amountAvailableForCapturing = 0;
        if ($payment->getChargedAmount() == 0) {
        $amountAvailableForCapturing = $payment->getOrderAmount() / 100;
        } else {
        $amountAvailableForCapturing = ($payment->getReservedAmount() - $payment->getChargedAmount()) / 100;
        }

         $amountAvailableForRefunding = 0;
        if ($payment->getChargedAmount() > 0 && $payment->getRefundedAmount() == 0) {
        $amountAvailableForRefunding = $payment->getChargedAmount() / 100;
        } else {
            if ($payment->getChargedAmount() - $payment->getRefundedAmount() > 0) {
            $amountAvailableForRefunding = ($payment->getChargedAmount() - $payment->getRefundedAmount()) / 100;
            }
        }
        
         
        $refundsArray = $payment->getAllRefund();
        $refundPendingStatus = false;
        if(!empty($refundsArray)){
        foreach($refundsArray as $row){
                if($row->state== 'Pending')
                {
                    $refundPendingStatus = true;
                }
            }
        }
        //echo "<pre>";print_r($recreafundPendingStatus);die;

         //return new JsonResponse(['obj'=>$payment->getPaymentData()]);exit;

        return new JsonResponse([
            'amountAvailableForCapturing' => $amountAvailableForCapturing,
            'amountAvailableForRefunding' => $amountAvailableForRefunding,
            'orderState' => $transaction->getStateMachineState()->getTechnicalName(),
            'refundPendingStatus' => $refundPendingStatus
        ]);
    }

    /**
     *
     * @RouteScope(scopes={"api"})
     * @Route("/api/nets/transaction/refund", name="nets.refund.payment.action", options={"seo"="false"}, methods={"POST"})
     * @param Context $context
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function refundPayment(Context $context, Request $request): JsonResponse
    {
        $orderId = $request->get('params')['orderId'];
        $paymentId = $request->get('params')['paymentId'];
        $amount = $request->get('params')['amount'];
        $orderEntity = $this->getOrderEntityById($context, $orderId);
        $salesChannelId = $this->getSalesChannelIdByOrderId($orderId, $context);

        try {
            $this->checkout->refundPayment($orderEntity, $salesChannelId, $context, $paymentId, $amount);
        } catch (EasyApiException $ex) {
            return new JsonResponse([
                'status' => false,
                'message' => $ex->getMessage(),
                'code' => $ex->getCode()
            ], Response::HTTP_BAD_REQUEST);
        } catch (Exception $ex) {
            return new JsonResponse([
                'status' => false,
                'message' => $ex->getMessage(),
                'code' => 0
            ], Response::HTTP_BAD_REQUEST);
        }
        return new JsonResponse([
            'status' => true
        ]);
    }

    /**
     *
     * @param Context $context
     * @param
     *            $orderId
     * @return mixed|null
     */
    private function getOrderEntityById(Context $context, $orderId)
    {
        $criteria = new Criteria([
            $orderId
        ]);
        $criteria->addAssociation('lineItems.payload')
            ->addAssociation('deliveries.shippingCosts')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('cartPrice.calculatedTaxes')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('currency')
            ->addAssociation('addresses.country')
            ->addAssociation('transactions.stateMachineState');
        return $this->orderRepository->search($criteria, $context)->first();
    }

    /**
     *
     * @throws OrderNotFoundException
     */
    private function getSalesChannelIdByOrderId(string $orderId, Context $context): string
    {
        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search(new Criteria([
            $orderId
        ]), $context)->first();

        if ($order === null) {
            throw new OrderNotFoundException($orderId);
        }

        return $order->getSalesChannelId();
    }

    /**
     *
     * @param
     *            $amount
     * @return int
     */
    private function prepareAmount($amount = 0)
    {
        return (int) round($amount * 100);
    }
}
