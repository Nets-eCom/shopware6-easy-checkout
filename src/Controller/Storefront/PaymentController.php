<?php

declare(strict_types=1);

namespace Nets\Checkout\Controller\Storefront;

use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Nets\Checkout\Service\DataReader\OrderDataReader;
use Nets\Checkout\Service\Easy\ConfigService;

/**
 * @RouteScope(scopes={"storefront"})
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
class PaymentController extends StorefrontController
{
    private EasyApiService $easyApiService;
    private ConfigService $configService;
    private CartService $cartService;
    private PaymentService $paymentService;
    private EntityRepository $netsApiRepository;
    private StateMachineRegistry $stateMachineRegistry;
    private AbstractCartOrderRoute $orderRoute;
    private RequestStack $requestStack;
    private EntityRepository $orderTransactionRepo;
    private OrderDataReader $orderDataReader;

    public function __construct(
        EasyApiService $easyApiService,
        ConfigService $configService,
        CartService $cartService,
        PaymentService $paymentService,
        EntityRepository $netsApiRepository,
        StateMachineRegistry $machineRegistry,
        AbstractCartOrderRoute $orderRoute,
        RequestStack $requestStack,
        EntityRepository $orderTransactionRepo,
        OrderDataReader $orderDataReader
    ) {
        $this->easyApiService       = $easyApiService;
        $this->configService        = $configService;
        $this->cartService          = $cartService;
        $this->paymentService       = $paymentService;
        $this->netsApiRepository    = $netsApiRepository;
        $this->stateMachineRegistry = $machineRegistry;
        $this->orderRoute           = $orderRoute;
        $this->requestStack         = $requestStack;
        $this->orderTransactionRepo = $orderTransactionRepo;
        $this->orderDataReader      = $orderDataReader;
    }

    /**
     * @Route("/nets/order/finish", name="nets.finish.order.controller", options={"seo": "false"}, methods={"GET"})
     *
     * @throws EasyApiException
     */
    public function placeOrder(Context $context, SalesChannelContext $ctx, Request $request, RequestDataBag $data): RedirectResponse
    {
        $cart = $this->cartService->getCart($ctx->getToken(), $ctx);

        try {
            $orderId = $this->orderRoute->order($cart, $ctx, $data)->getOrder()->getId();
        } catch (\Exception $e) {
        }

        if (empty($orderId)) {
            $orderId = $request->getSession()->get('orderId');
        }

        $orderEntity    = $this->orderDataReader->getOrderEntityById($context, $orderId);
        $transaction    = $orderEntity->getTransactions()->first();
        $payment     = $this->easyApiService->getPayment($this->requestStack->getCurrentRequest()->get('paymentId'));
        $checkoutUrl = $payment->getCheckoutUrl();
        $refUpdate   = [
            'reference'   => $orderEntity->getOrderNumber(),
            'checkoutUrl' => $checkoutUrl,
        ];
        $this->easyApiService->updateReference($this->requestStack->getCurrentRequest()->get('paymentId'), json_encode($refUpdate));

        // For inserting amount available respect to charge id
        if ($this->configService->getChargeNow($ctx->getsalesChannel()->getId()) == 'yes' || $payment->getPaymentType() == 'A2A') {
            $this->netsApiRepository->create([
            [
            'order_id'         => $orderId ? $orderId : '',
            'charge_id'        => $payment->getFirstChargeId() ? $payment->getFirstChargeId() : '',
            'operation_type'   => 'capture',
            'operation_amount' => $payment->getChargedAmount() ? $payment->getChargedAmount() / 100 : '',
            'amount_available' => $payment->getChargedAmount() ? $payment->getChargedAmount() / 100 : '',
            ],
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
                   'id'           => $transaction->getId(),
                   'customFields' => [
                       'nets_easy_payment_details' => [
                           'transaction_id' => $this->requestStack->getCurrentRequest()->get('paymentId'),
                           'can_capture'    => true,
                       ],
                   ],
               ],
           ], $context);

        $finishUrl = $this->generateUrl('frontend.checkout.finish.page',
            [
                'orderId' => $orderId,
            ]);

        try {
            $this->paymentService->handlePaymentByOrder($orderId, $data, $ctx, $finishUrl);

            return new RedirectResponse($finishUrl);
        } catch (\Exception $e) {
        }
    }
}
