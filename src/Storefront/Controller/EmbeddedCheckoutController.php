<?php

declare(strict_types=1);

namespace Nets\Checkout\Storefront\Controller;

use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Exception\EmptyCartException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRoute;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
class EmbeddedCheckoutController extends StorefrontController
{
    private AbstractCartOrderRoute $cartOrderRoute;
    private CartService $cartService;
    private HandlePaymentMethodRoute $handlePaymentMethodRoute;

    public function __construct(
        AbstractCartOrderRoute   $route,
        CartService              $cartService,
        HandlePaymentMethodRoute $handlePaymentMethodRoute
    ) {
        $this->cartOrderRoute = $route;
        $this->cartService = $cartService;
        $this->handlePaymentMethodRoute = $handlePaymentMethodRoute;
    }

    /**
     * @Route(
     *     "/nets/handle-payment",
     *     name="frontend.nets.handle_payment",
     *     methods={"POST"},
     *     defaults={"XmlHttpRequest"=true, "csrf_protected"=false}
     * )
     */
    public function handle(Request $request, RequestDataBag $data, SalesChannelContext $salesChannelContext): Response
    {
        try {
            $order = $this->createOrder($salesChannelContext, $data);
        } catch (EmptyCartException $exception) {
            return $this->json(
                [
                    'errorUrl' => $this->generateUrl('frontend.checkout.cart.page')
                ],
                $exception->getStatusCode()
            );
        }

        $request->request->set('orderId', $order->getId());
        $request->request->set('finishUrl', $this->generateUrl(
            'frontend.checkout.finish.page',
            [
                'orderId' => $order->getId(),
            ]
        ));
        $request->request->set('errorUrl', $this->generateUrl('frontend.checkout.cart.page'));

        return $this->handlePaymentMethodRoute->load($request, $salesChannelContext);
    }

    /**
     * @throws EmptyCartException
     */
    private function createOrder(SalesChannelContext $salesChannelContext, RequestDataBag $data): OrderEntity
    {
        return $this
            ->cartOrderRoute
            ->order(
                $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext),
                $salesChannelContext,
                $data
            )
            ->getOrder();
    }
}