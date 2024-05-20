<?php

declare(strict_types=1);

namespace Nets\Checkout\Storefront\Controller;

use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Exception\EmptyCartException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class EmbeddedCheckoutController extends StorefrontController
{
    public const SESSION_NETS_PAYMENT_ID = 'nets_payment_payment_id';

    private AbstractCartOrderRoute $cartOrderRoute;
    private CartService $cartService;
    private HandlePaymentMethodRoute $handlePaymentMethodRoute;
    private EntityRepository $transactionRepository;

    public function __construct(
        AbstractCartOrderRoute $route,
        CartService $cartService,
        HandlePaymentMethodRoute $handlePaymentMethodRoute,
        EntityRepository $transactionRepository
    ) {
        $this->cartOrderRoute = $route;
        $this->cartService = $cartService;
        $this->handlePaymentMethodRoute = $handlePaymentMethodRoute;
        $this->transactionRepository = $transactionRepository;
    }

    #[Route(path: '/nets/handle-payment', name: 'frontend.nets.handle_payment', defaults: ['XmlHttpRequest' => true, 'csrf_protected' => false], methods: ['POST'])]
    public function handle(Request $request, RequestDataBag $data, SalesChannelContext $salesChannelContext): Response
    {
        try {
            $order = $this->createOrder($salesChannelContext, $data);
            $orderId = $order->getId();
            $session = $request->getSession();

            $this->saveTransactionId(
                $session,
                $salesChannelContext->getContext(),
                $order->getTransactions()->first()->getId()
            );

        } catch (EmptyCartException $exception) {
            return $this->json(
                [
                    'errorUrl' => $this->generateUrl('frontend.checkout.cart.page')
                ],
                $exception->getStatusCode()
            );
        }

        $request->request->set('orderId', $orderId);
        $request->request->set(
            'finishUrl',
            $this->generateUrl('frontend.checkout.finish.page', ['orderId' => $orderId])
        );
        $request->request->set(
            'errorUrl',
            $this->generateUrl('frontend.account.edit-order.page', ['orderId' => $orderId])
        );

        $response = $this->handlePaymentMethodRoute->load($request, $salesChannelContext);

        $session->remove(self::SESSION_NETS_PAYMENT_ID);

        return $response;
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

    private function saveTransactionId(Session $session, Context $context, string $transactionId): void
    {
        $this->transactionRepository->update(
            [
                [
                    'id' => $transactionId,
                    'customFields' => [
                        'nets_easy_payment_details' => [
                            'transaction_id' => $session->get(self::SESSION_NETS_PAYMENT_ID),
                        ],
                    ],
                ]
            ],
            $context
        );
    }
}