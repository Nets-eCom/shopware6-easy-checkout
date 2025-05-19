<?php

declare(strict_types=1);

namespace Nexi\Checkout\Storefront\Controller;

use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Shopware\Core\Checkout\Cart\Exception\InvalidCartException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Exception\EmptyCartException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[
    Route(
        '/nexicheckout/embedded',
        name: 'nexicheckout_',
        defaults: [
            '_routeScope' => ['storefront'],
        ]
    )
]
class EmbeddedController extends StorefrontController
{
    /**
     * @param EntityRepository<OrderTransactionCollection> $transactionRepository
     */
    public function __construct(
        private readonly HandlePaymentMethodRoute $handlePaymentMethodRoute,
        private readonly CartOrderRoute $cartOrderRoute,
        private readonly CartService $cartService,
        private readonly EntityRepository $transactionRepository
    ) {
    }

    #[Route(
        '/handle-payment',
        name: 'payment.nexicheckout.embedded.handle-payment',
        defaults: [
            'XmlHttpRequest' => true,
            'csrf_protected' => false,
        ],
        methods: ['POST']
    )]
    public function handle(Request $request, RequestDataBag $data, SalesChannelContext $salesChannelContext): Response
    {
        try {
            $order = $this
                ->cartOrderRoute
                ->order(
                    $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext),
                    $salesChannelContext,
                    $data
                )
                ->getOrder();
        } catch (InvalidCartException|EmptyCartException $e) {
            $this->addCartErrors(
                $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext)
            );

            return $this->json(
                [
                    'targetPath' => $this->generateUrl('frontend.checkout.cart.page'),
                ],
                $e->getStatusCode()
            );
        } catch (PaymentException $e) {
            $this->addFlash(StorefrontController::DANGER, $e->getMessage());

            return $this->json(
                [
                    'targetPath' => $this->generateUrl('frontend.checkout.confirm.page'),
                ],
                $e->getStatusCode()
            );
        }

        return $this->json([
            'targetPath' => $this->handlePayment($order, $request, $salesChannelContext),
        ]);
    }

    #[Route(
        '/checkout-confirm',
        name: 'payment.nexicheckout.embedded.confirm',
        options: [
            'seo' => false,
        ],
        defaults: [
            'XmlHttpRequest' => true,
            '_noStore' => true,
        ],
        methods: ['GET']
    )]
    public function confirm(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $paymentId = $request->query->getString('paymentId');

        if ($paymentId === '') {
            throw new BadRequestHttpException('Missing paymentId parameter');
        }

        $transaction = $this->findTransaction($paymentId, $salesChannelContext->getContext());

        if (!$transaction instanceof OrderTransactionEntity) {
            $this->addFlash('danger', $this->trans('nexi-checkout.exception.missingTransaction'));

            return $this->redirectToRoute('frontend.checkout.confirm.page');
        }

        return $this->redirectToRoute('frontend.checkout.finish.page', [
            'orderId' => $transaction->getOrderId(),
        ]);
    }

    private function handlePayment(
        OrderEntity $order,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): string {
        $orderId = $order->getId();
        $finishUrl = $this->generateUrl('frontend.checkout.finish.page', [
            'orderId' => $orderId,
        ]);
        $errorUrl = $this->generateUrl('frontend.account.edit-order.page', [
            'orderId' => $orderId,
        ]);

        $request->request->set('orderId', $orderId);
        $request->request->set('finishUrl', $finishUrl);
        $request->request->set('errorUrl', $errorUrl);

        $routeResponse = $this->handlePaymentMethodRoute->load($request, $salesChannelContext);

        return $routeResponse->getRedirectResponse()?->getTargetUrl() ?? $finishUrl;
    }

    private function findTransaction(
        string $paymentId,
        Context $context
    ): ?OrderTransactionEntity {
        $criteria = (new Criteria())
            ->addAssociation('stateMachineState')
            ->addFilter(
                new EqualsFilter(
                    OrderTransactionDictionary::CUSTOM_FIELDS_PREFIX . OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID,
                    $paymentId
                )
            );

        /** @var OrderTransactionCollection $transactions */
        $transactions = $this->transactionRepository
            ->search($criteria, $context)
            ->getEntities();

        return $transactions->first();
    }
}
