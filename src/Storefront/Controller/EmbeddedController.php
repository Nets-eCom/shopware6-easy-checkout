<?php

declare(strict_types=1);

namespace Nexi\Checkout\Storefront\Controller;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\Lifecycle\PaymentMethodsInstaller;
use Nexi\Checkout\RequestBuilder\PaymentRequest;
use Nexi\Checkout\Subscriber\EmbeddedCreatePaymentOnCheckoutSubscriber;
use NexiCheckout\Api\Exception\PaymentApiException;
use NexiCheckout\Factory\PaymentApiFactory;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Exception\InvalidCartException;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Exception\EmptyCartException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractHandlePaymentMethodRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[
    Route(
        '/nexicheckout/embedded',
        name: 'payment.nexicheckout.embedded.', // Route name has to start with "payment." to be recognized as Storefront payment route
        defaults: [
            '_routeScope' => ['storefront'],
        ]
    )
]
class EmbeddedController extends StorefrontController
{
    /**
     * @param EntityRepository<OrderTransactionCollection> $transactionRepository
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        private readonly AbstractHandlePaymentMethodRoute $handlePaymentMethodRoute,
        private readonly AbstractCartOrderRoute $cartOrderRoute,
        private readonly CartService $cartService,
        private readonly EntityRepository $transactionRepository,
        private readonly PaymentRequest $paymentRequest,
        private readonly PaymentApiFactory $paymentApiFactory,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly LoggerInterface $logger,
        private readonly SalesChannelContextPersister $contextPersister,
        private readonly EntityRepository $paymentMethodRepository,
    ) {
    }

    #[Route(
        '/create-embedded',
        name: 'create-embedded',
        defaults: [
            'XmlHttpRequest' => true,
            'csrf_protected' => false,
        ],
        methods: ['POST']
    )]
    public function createEmbedded(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $subselection = $request->request->getString('subselection');

        if ($subselection === '') {
            return new JsonResponse([
                'error' => 'Missing subselection',
            ], Response::HTTP_BAD_REQUEST);
        }

        $salesChannelId = $salesChannelContext->getSalesChannelId();

        try {
            $secretKey = $this->configurationProvider->getSecretKey($salesChannelId);
        } catch (\Throwable) {
            return new JsonResponse([
                'error' => 'Wrong configuration',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($secretKey === '') {
            return new JsonResponse([
                'error' => 'Missing API key',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);
            $paymentApi = $this->paymentApiFactory->create(
                $secretKey,
                $this->configurationProvider->isLiveMode($salesChannelId)
            );
            $paymentRequest = $this->paymentRequest->buildEmbedded($cart, $salesChannelContext, $subselection);
            $payment = $paymentApi->createEmbeddedPayment($paymentRequest);

            $request->getSession()->set(
                EmbeddedCreatePaymentOnCheckoutSubscriber::SESSION_NEXI_PAYMENT_ORDER,
                $paymentRequest->getOrder()
            );

            $this->logger->info('Split embedded payment created', [
                'paymentId' => $payment->getPaymentId(),
                'subselection' => $subselection,
            ]);

            $this->switchContextToEmbeddedPaymentMethod($salesChannelContext);

            return new JsonResponse([
                'paymentId' => $payment->getPaymentId(),
            ]);
        } catch (PaymentApiException $paymentApiException) {
            $this->logger->error('Failed to create split embedded payment', [
                'message' => $paymentApiException->getMessage(),
                'subselection' => $subselection,
            ]);

            return new JsonResponse([
                'error' => $paymentApiException->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(
        '/handle-payment',
        name: 'handle-payment',
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
        name: 'confirm',
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

    private function switchContextToEmbeddedPaymentMethod(SalesChannelContext $salesChannelContext): void
    {
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('technicalName', PaymentMethodsInstaller::NEXI_CHECKOUT_EMBEDDED_TECHNICAL_NAME)
        );

        $embeddedMethod = $this->paymentMethodRepository
            ->search($criteria, $salesChannelContext->getContext())
            ->first();

        if ($embeddedMethod === null) {
            return;
        }

        $this->contextPersister->save(
            $salesChannelContext->getToken(),
            [
                'paymentMethodId' => $embeddedMethod->getId(),
            ],
            $salesChannelContext->getSalesChannelId(),
        );
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
