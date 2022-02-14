<?php
declare(strict_types = 1);
namespace Nets\Checkout\Storefront\Controller;

use Shopware\Core\System\StateMachine\StateMachineRegistry;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\OrderStates;
use shopware\core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute;

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
	
    private $transHandler;
	
	private $machineRegistry;
	
	private $orderRoute; 
    public function __construct(EntityRepositoryInterface $orderRepository, \Psr\Log\LoggerInterface $logger, \Nets\Checkout\Service\Easy\CheckoutService $checkout, SystemConfigService $systemConfigService, EasyApiService $easyApiService, \Symfony\Component\HttpKernel\KernelInterface $kernel, ConfigService $configService, CartService $cartService, PaymentService $paymentService,EntityRepositoryInterface $netsApiRepository, OrderTransactionStateHandler $transHandler, StateMachineRegistry $machineRegistry, CartOrderRoute $orderRoute)
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
		$this->transHandler = $transHandler;
		$this->stateMachineRegistry = $machineRegistry;
		$this->orderRoute = $orderRoute;
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
		
		try {
		$orderId = $this->orderRoute->order($cart, $ctx, $data)->getOrder()->getId();
		} Catch(\Exception $e){
		}
		if(empty($orderId)){
			$orderId = $_SESSION['orderId'];
		}
		
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


        $finishUrl = $this->generateUrl('frontend.checkout.finish.page',
		[
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

		$orderStatus = $orderEntity->getStateMachineState()->getTechnicalName();

		if($orderStatus == OrderStates::STATE_CANCELLED && $payment->getCancelledAmount() <= 0 ){
			$secretKey = $this->configService->getSecretKey($salesChannelId);
			$environment = $this->configService->getEnvironment($salesChannelId);
			$this->easyApiService->setEnv($environment);
			$this->easyApiService->setAuthorizationKey($secretKey);
			
			$cancelBody = [
                    'amount' => $payment->getReservedAmount(),
                    'orderItems' => ''
                ];

			$paymentVoid = $this->easyApiService->voidPayment($paymentId, json_encode($cancelBody));
			if(!empty($paymentVoid)) {
				 $this->transHandler->cancel($orderEntity->getTransactions()->first()->getId(), $context);
			}
					 
		}
	
	   if($payment->getChargedAmount() > 0 ) {
			$response = $payment->getAllCharges();
			foreach($response as $value => $key) {
			
			$criteria = new Criteria();
			$criteria->addFilter(new EqualsFilter('charge_id', $key->chargeId));
			$chargeId = $this->netsApiRepository->search($criteria, $context)->first();
			
			if(empty($chargeId)) {
				$this->netsApiRepository->create([
				[
				'order_id' => $orderId?$orderId:'',
				'charge_id' => $key->chargeId,
				'operation_type' =>'capture',
				'operation_amount' => $key->amount / 100,
				'amount_available' => $key->amount / 100,
				]
				], $context);	
			}
			}	
		}
        
	
		if($payment->getRefundedAmount() == 0) {
			if($payment->getReservedAmount() == $payment->getChargedAmount()){
				if($transaction->getStateMachineState()->getTechnicalName() == "paid_partially") {
					 $this->transHandler->reopen($orderEntity->getTransactions()->first()
						->getId(), $context);
					 
					$this->stateMachineRegistry->transition(new Transition(
						OrderTransactionDefinition::ENTITY_NAME,
						$orderEntity->getTransactions()->first()
						->getId(),
						StateMachineTransitionActions::ACTION_PAID,
						'stateId'
					), $context);
				} else {
					 $this->transHandler->paid($orderEntity->getTransactions()->first()->getId(), $context);
				}
			}else if ($payment->getChargedAmount() < $payment->getReservedAmount() && $payment->getChargedAmount() > 0){
				$this->transHandler->payPartially($orderEntity->getTransactions()->first()
						->getId(), $context);	
					
			} 
		}
		if($payment->getRefundedAmount() > 0 ) {
			if($payment->getChargedAmount() == $payment->getRefundedAmount()){
			$this->transHandler->refund($orderEntity->getTransactions()->first()
                    ->getId(), $context);	
			} else if ($payment->getRefundedAmount() < $payment->getChargedAmount()){
				$this->transHandler->refundPartially($orderEntity->getTransactions()->first()
						->getId(), $context);	
			}
		}
		
		
		  // Refund functionality 
        $chargeArrWithAmountAvailable = array();
		$chargeIdArr = array();
        $chargeIdArr = $payment->getAllCharges();
        $refundResult = false;
		if(!empty($chargeIdArr)) {
        foreach ($chargeIdArr as $row) {
            // select query based on charge to get amount available
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('charge_id', $row->chargeId));
            $result = $this->netsApiRepository->search($criteria, $context)->first();
            if ($result) {
                $chargeArrWithAmountAvailable[$row->chargeId] = $result->amount_available;
            }
        }
        array_multisort($chargeArrWithAmountAvailable, SORT_DESC);
		
		}
		
        // second block
			$refundsArray = $payment->getAllRefund();
			
			
		if($payment->getRefundedAmount() > 0 ) {
			
			foreach($refundsArray as $vl => $ky) {
				$amountToRefund = $ky->amount / 100;
				$refundChargeIdArray = array();
				foreach ($chargeArrWithAmountAvailable as $key => $value) {
					$criteria = new Criteria();
					$criteria->addFilter(new EqualsFilter('charge_id', $key));
					$resultCharge = $this->netsApiRepository->search($criteria, $context)->first();
					if($resultCharge->amount_available > 0 ) {
						if ($amountToRefund <= $value) {
							$refundChargeIdArray[$key] = $amountToRefund;
							break;
						}
						if ($ky->amount >= $value) {
							$ky->amount = $ky->amount - $value;
							$refundChargeIdArray[$key] = $value;
						} else {
							$refundChargeIdArray[$key] = $ky->amount;
						}
						if (array_sum($refundChargeIdArray) == $amountToRefund) {
							break;
						}
					}
				}

				// third block
				if ($amountToRefund <= array_sum($refundChargeIdArray)) {
					$refundResult = true;
					$count = 0;
					foreach ($refundChargeIdArray as $key => $value) {
						// update table for amount available
						if ($refundResult) {
							// get amount available based on charge id
							$criteria = new Criteria();
							$criteria->addFilter(new EqualsFilter('charge_id', $key));
							$result = $this->netsApiRepository->search($criteria, $context)->first();
							if ($result) {
								$availableAmount = $result->amount_available - $value;
								$update = [
									'id' => $result->id,
									'amount_available' => $availableAmount
								];
								$this->netsApiRepository->update([
									$update
								], $context);
							}
						}
					}
				}
			}	 
        }
        $refundPendingStatus = false;
        if(!empty($refundsArray)){
        foreach($refundsArray as $row){
                if($row->state== 'Pending')
                {
                    $refundPendingStatus = true;
                }
            }
        }
		

        return new JsonResponse([
            'amountAvailableForCapturing' => $amountAvailableForCapturing,
            'amountAvailableForRefunding' => ($payment->getChargedAmount() - $payment->getRefundedAmount()) / 100,
            'orderState' => $transaction->getStateMachineState()->getTechnicalName(),
            'refundPendingStatus' => $refundPendingStatus,
			'paymentMethod' => $payment->getPaymentMethod()
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
	
	/**
     * @RouteScope(scopes={"storefront"})
     * @Route("/api/nets/transaction/capturewebhook", name="nets.capture.webhook.action", options={"seo"="false"}, methods={"POST"})
     *                                          
     *
     * @param SalesChannelContext $context
     * @param                     $transactionId
     *
     * @return JsonResponse
     */
    public function captureWebhook(Context $context, Request $request): JsonResponse
    {
        try {
			echo "here"; print_R($request->toArray);
            
			$this->netsApiRepository->create([
            [
            'order_id' => '123',
            'charge_id' => '123test',
            'operation_type' =>'capture',
            'operation_amount' => 10,
            'amount_available' => 10,
            ]
            ], $context);
			
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

	
	
   
}
