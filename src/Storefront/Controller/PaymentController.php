<?php
declare(strict_types = 1);
namespace Nets\Checkout\Storefront\Controller;

use Symfony\Component\HttpFoundation\RequestStack;
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
use Shopware\Core\Checkout\Order\OrderDefinition;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

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
	
	private $requestStack;
	
	private $session; 
	
	private $orderTransactionRepo; 
	
	private $router; 
	
	private $pluginRepo;
	

	
    public function __construct(EntityRepositoryInterface $orderRepository, \Psr\Log\LoggerInterface $logger, \Nets\Checkout\Service\Easy\CheckoutService $checkout, SystemConfigService $systemConfigService, EasyApiService $easyApiService, \Symfony\Component\HttpKernel\KernelInterface $kernel, ConfigService $configService, CartService $cartService, PaymentService $paymentService,EntityRepositoryInterface $netsApiRepository, OrderTransactionStateHandler $transHandler, StateMachineRegistry $machineRegistry, CartOrderRoute $orderRoute, RequestStack $requestStack, SessionInterface $session, EntityRepositoryInterface $orderTransactionRepo, EntityRepositoryInterface $pluginRepo)
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
		$this->requestStack = $requestStack;
		$this->session = $session;
		$this->orderTransactionRepo = $orderTransactionRepo;
		$this->pluginRepo = $pluginRepo;
			
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
    public function placeOrder(Context $context, SalesChannelContext $ctx, Request $request, RequestDataBag $data) : RedirectResponse
    {
		
        $cart = $this->cartService->getCart($ctx->getToken(), $ctx);
		
		try {
		$orderId = $this->orderRoute->order($cart, $ctx, $data)->getOrder()->getId();
		//$orderId = $this->cartService->order($cart, $ctx, $data);
		} Catch(\Exception $e){
		}
		if(empty($orderId)){
			$orderId = $this->session->get('orderId');
		}
		
        $orderEntity = $this->getOrderEntityById($context, $orderId);
		$transaction = $orderEntity->getTransactions()->first();
        $salesChannelId = $ctx->getSalesChannel()->getId();
        $secretKey = $this->configService->getSecretKey($salesChannelId);
        $environment = $this->configService->getEnvironment($salesChannelId);
        $this->easyApiService->setEnv($environment);
        $this->easyApiService->setAuthorizationKey($secretKey);
        $payment = $this->easyApiService->getPayment($this->requestStack->getCurrentRequest()->get('paymentId'));
        $checkoutUrl = $payment->getCheckoutUrl();
        $refUpdate = [
            'reference' => $orderEntity->getOrderNumber(),
            'checkoutUrl' => $checkoutUrl
        ];
        $this->easyApiService->updateReference($this->requestStack->getCurrentRequest()->get('paymentId'), json_encode($refUpdate));

        //For inserting amount available respect to charge id
        if($this->configService->getChargeNow($ctx->getsalesChannel()->getId()) == 'yes' || $payment->getPaymentType() == 'A2A'){

            $this->netsApiRepository->create([
            [
            'order_id' => $orderId?$orderId:'',
            'charge_id' => $payment->getFirstChargeId()?$payment->getFirstChargeId():'',
            'operation_type' =>'capture',
            'operation_amount' => $payment->getChargedAmount()?$payment->getChargedAmount()/100:'',
            'amount_available' => $payment->getChargedAmount()?$payment->getChargedAmount()/100:'',
            ]
            ], $context);
			
			$this->stateMachineRegistry->transition(new Transition(
						OrderTransactionDefinition::ENTITY_NAME,
						$orderEntity->getTransactions()->first()
						->getId(),
						StateMachineTransitionActions::ACTION_PAID,
						'stateId'
					), $context);
        }

		 $this->orderTransactionRepo->update([
                [
                    'id' => $transaction->getId(),
                    'customFields' => [
                        'nets_easy_payment_details' => [
                            'transaction_id' => $this->requestStack->getCurrentRequest()->get('paymentId'),
                            'can_capture' => true
                        ]
                    ]
                ]
            ], $context);


        $finishUrl = $this->generateUrl('frontend.checkout.finish.page',
		[
            'orderId' => $orderId
        ]);
		
			try{
				$result =  $this->paymentService->handlePaymentByOrder($orderId, $data, $ctx, $finishUrl);
				return new RedirectResponse($finishUrl); 
			} Catch(Exception $e){
			}
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
            $payment = $this->easyApiService->getPayment($this->requestStack->getCurrentRequest()->get('paymentid'));
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
	
		$orderStatus = $orderEntity->getStateMachineState()->getTechnicalName();
		$amountAvailableForCapturing = 0;
		$amountAvailableForRefunding = 0;
		$refundPendingStatus = false;

		if($orderStatus == OrderStates::STATE_CANCELLED){
			$secretKey = $this->configService->getSecretKey($salesChannelId);
			$environment = $this->configService->getEnvironment($salesChannelId);
			$this->easyApiService->setEnv($environment);
			$this->easyApiService->setAuthorizationKey($secretKey);
			
			$this->transHandler->cancel($orderEntity->getTransactions()->first()->getId(), $context);
			
			if($transaction->getStateMachineState()->getTechnicalName() == OrderStates::STATE_CANCELLED){
				$payment = $this->easyApiService->getPayment($paymentId);
	
				if(empty($payment->getCancelledAmount())){
					$cancelBody = [
						'amount' => $payment->getReservedAmount()
					];

					try {
						$paymentVoid = $this->easyApiService->voidPayment($paymentId, json_encode($cancelBody));
						}catch(Exception $e) {
						
						}
				}
			}
			
			
		} else  {
      
        if ($payment->getChargedAmount() == 0) {
        $amountAvailableForCapturing = $payment->getOrderAmount() / 100;
        } else {
        $amountAvailableForCapturing = ($payment->getReservedAmount() - $payment->getChargedAmount()) / 100;
        }

        
        if ($payment->getChargedAmount() > 0 && $payment->getRefundedAmount() == 0) {
        $amountAvailableForRefunding = $payment->getChargedAmount() / 100;
        } else {
            if ($payment->getChargedAmount() - $payment->getRefundedAmount() > 0) {
            $amountAvailableForRefunding = ($payment->getChargedAmount() - $payment->getRefundedAmount()) / 100;
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
        $remainingAmount = null;

		if(!empty($chargeIdArr)) {
        foreach ($chargeIdArr as $row) {
            // select query based on charge to get amount available
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('charge_id', $row->chargeId));
            $result = $this->netsApiRepository->search($criteria, $context)->first();
            if ($result) {
                $chargeArrWithAmountAvailable[$row->chargeId] = $result->amount_available;
                $remainingAmount +=$result->amount_available;
            }
        }
        array_multisort($chargeArrWithAmountAvailable, SORT_DESC);
		
		}
		
        // second block
        $refundsArray = $payment->getAllRefund();
			
        $amountAvailableForRefunding = ($payment->getChargedAmount() - $payment->getRefundedAmount())/100;
      
        if ($payment->getRefundedAmount() > 0 && $remainingAmount != $amountAvailableForRefunding) {
			
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
				'paymentMethod' => $payment->getPaymentMethod(),
				'refunded'=> $payment->getRefundedAmount()
			]);
		}
        
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
     *
     * @RouteScope(scopes={"storefront"})
     * @Route("/nets/order/cancel", name="nets.cancel.order.controller", options={"seo"="false"}, methods={"GET"})
     * @param Context $context
     * @param SalesChannelContext $ctx
     * @param Request $request
     * @param RequestDataBag $data
     * @return RedirectResponse|null
     * @throws EasyApiException
     */
	public function cancelOrder(Context $context, SalesChannelContext $ctx, Request $request, RequestDataBag $data){
	
		$orderId = $this->session->get('sw_order_id');
		$orderNo = $this->session->get('cancelOrderId');
		$salesChannelId = $this->getSalesChannelIdByOrderId($orderId, $context);
		$orderEntity = $this->getOrderEntityById($context, $orderId);
		$transaction = $orderEntity->getTransactions()->first();
			
		$this->stateMachineRegistry->transition(new Transition(
					OrderDefinition::ENTITY_NAME,
					$orderId,
					StateMachineTransitionActions::ACTION_CANCEL,
					'stateId'
				), $context);
				
		$this->transHandler->cancel($orderEntity->getTransactions()->first()->getId(), $context);
		return new RedirectResponse($this->requestStack->getCurrentRequest()->getUriForPath('/checkout/cart'));
	}
	
	/**
	 * @RouteScope(scopes={"api"})
	 * @Route("/api/test/verify", name="nets.api.test.controller",defaults={"XmlHttpRequest"=true},options={"seo"="false"}, methods={"POST"})
     */
    public function check(Context $context, Request $request, RequestDataBag $dataBag): JsonResponse
    {
       $environment = $dataBag->get("NetsCheckout.config.enviromnent"); 
	   $checkoutType = $dataBag->get("NetsCheckout.config.checkoutType");
	   $success = false;
	   if($environment == "test"){
		   $secretKey = $dataBag->get("NetsCheckout.config.testSecretKey");
		   if($checkoutType == "embedded"){
			   $checkoutKey = $dataBag->get("NetsCheckout.config.testCheckoutKey");
		   }
	   } else {
		   $secretKey = $dataBag->get("NetsCheckout.config.liveSecretKey");
		   if($checkoutType == "embedded"){
			   $checkoutKey = $dataBag->get("NetsCheckout.config.liveCheckoutKey");
		   }
	   }
	   
	    if($checkoutType == "hosted"){
			if(empty($secretKey)){
			return new JsonResponse(['success' => $success]);
			}
			$integrationType = "HostedPaymentPage";
			$url = '"returnUrl": "https://localhost","cancelUrl": "https://localhost"';
		} else {
			if(empty($secretKey) or empty($checkoutKey)){
			return new JsonResponse(['success' => $success]);
			}
			$integrationType = "EmbeddedCheckout";
			$url = '"url": "https://localhost"';
		}
		
		
	   $payload = '{
				  "checkout": {
					"integrationType": "'. $integrationType .'",
					"termsUrl": "'. $dataBag->get("NetsCheckout.config.termsUrl").'",
					"merchantHandlesConsumerData":true,'.
					$url .'
				  },
				  "order": {
					"items": [
					  {
						"reference": "Test001",
						"name": "Demo product",
						"quantity": 1,
						"unit": "pcs",
						"unitPrice": 1000,
						"grossTotalAmount": 1000,
						"netTotalAmount": 1000
					  }
					],
					"amount": 1000,
					"currency": "SEK",
					"reference": "Demo Test Order"
				  }
				}';
	   

	    $this->easyApiService->setEnv($environment);
        $this->easyApiService->setAuthorizationKey($secretKey);
		
		$result = $this->easyApiService->createPayment($payload);
		if($result) {
			$response = json_decode($result,true); 
			if(!empty($response['paymentId'])){
				$success = true;
			} 
		}
        return new JsonResponse(['success' => $success]);
    }
	
	 
	/**
	 * @RouteScope(scopes={"api"})
	 * @Route("/api/pluginversion", name="nets.api.custom.controller",defaults={"XmlHttpRequest"=true},options={"seo"="false"}, methods={"POST"})
     */
    public function customApi(Context $context, Request $request, RequestDataBag $dataBag): JsonResponse
    {
		
       $environment = $this->systemConfigService->get("NetsCheckout.config.enviromnent"); 
	   $checkoutType = $this->systemConfigService->get("NetsCheckout.config.checkoutType");
	   $merchantId = $this->systemConfigService->get("NetsCheckout.config.merchantId");

	   $success = false;
	   if($environment == "test"){
		   $secretKey = $dataBag->get("NetsCheckout.config.testSecretKey");
		   if($checkoutType == "embedded"){
			   $checkoutKey = $dataBag->get("NetsCheckout.config.testCheckoutKey");
		   }
	   } else {
		   $secretKey = $dataBag->get("NetsCheckout.config.liveSecretKey");
		   if($checkoutType == "embedded"){
			   $checkoutKey = $dataBag->get("NetsCheckout.config.liveCheckoutKey");
		   }
	   }
	   
		
			$criteria = new Criteria();
			$criteria->addFilter(new EqualsFilter('name', "NetsCheckout"));
			$resultData = $this->pluginRepo->search($criteria, $context)->first();
	

	
		    $dataArray = array('merchant_id' => $merchantId , //merchant Id
				'merchant_email_id' => "",
				'plugin_name' => "Shopware6", //plugin Name
				'plugin_version' => $resultData->version, //plugin version
				'shop_url' => getenv("APP_URL"), //shop url
				'integration_type' => $checkoutType,
				'timestamp' => date('Y-m-d H:i:s'),
				"env" => $environment
			);
			$postData = json_encode($dataArray);
	
		    $result = $this->easyApiService->getPluginVersion($postData);

			if($result) {   
				$response = json_decode($result,true);
				if($response['status']=="00") {
					return new JsonResponse(json_decode($response['data'], true));
				} else { 
				  return new JsonResponse(array('res' => 0));
				}
			} else {
				return new JsonResponse(array('res' => 0));
			}	
       
		
	}
}
