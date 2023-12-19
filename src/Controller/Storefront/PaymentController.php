<?php

declare(strict_types=1);

namespace Nets\Checkout\Controller\Storefront;

use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Nets\Checkout\Service\DataReader\OrderDataReader;

/**
 * @RouteScope(scopes={"storefront"})
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
class PaymentController extends StorefrontController
{
    private EasyApiService $easyApiService;
    private CartService $cartService;
    private AbstractCartOrderRoute $orderRoute;
    private RequestStack $requestStack;
    private EntityRepository $orderTransactionRepo;
    private OrderDataReader $orderDataReader;
    private TokenFactoryInterfaceV2 $tokenFactory;

    public function __construct(
        EasyApiService $easyApiService,
        CartService $cartService,
        AbstractCartOrderRoute $orderRoute,
        RequestStack $requestStack,
        EntityRepository $orderTransactionRepo,
        OrderDataReader $orderDataReader,
        TokenFactoryInterfaceV2 $tokenFactory
    ) {
        $this->easyApiService       = $easyApiService;
        $this->cartService          = $cartService;
        $this->orderRoute           = $orderRoute;
        $this->requestStack         = $requestStack;
        $this->orderTransactionRepo = $orderTransactionRepo;
        $this->orderDataReader      = $orderDataReader;
        $this->tokenFactory = $tokenFactory;
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

        $this->easyApiService->updateReference($this->requestStack->getCurrentRequest()->get('paymentId'), json_encode([
            'reference'   => $orderEntity->getOrderNumber(),
            'checkoutUrl' => $payment->getCheckoutUrl(),
        ]));

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
           ],
            $context
        );

        $tokenStruct = new TokenStruct(
            null,
            null,
            $transaction->getPaymentMethodId(),
            $transaction->getId(),
            $this->generateUrl(
                'frontend.checkout.finish.page', ['orderId' => $orderId]
            ),
        );

        return $this->redirectToRoute(
            'payment.finalize.transaction',
            [
                '_sw_payment_token' => $this->tokenFactory->generateToken($tokenStruct),
                'paymentid' => $this->requestStack->getCurrentRequest()->get('paymentId')
            ]
        );
    }
}
