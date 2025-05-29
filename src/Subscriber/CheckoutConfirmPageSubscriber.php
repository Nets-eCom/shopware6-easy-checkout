<?php

namespace Nets\Checkout\Subscriber;

use Nets\Checkout\Exception\CartTotalUpdatedException;
use Nets\Checkout\Service\Checkout;
use Nets\Checkout\Service\Easy\Api\Payment;
use Nets\Checkout\Service\Easy\ConfigService;
use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Nets\Checkout\Service\Easy\Api\TransactionDetailsStruct;
use Nets\Checkout\Service\Easy\CheckoutService;
use Nets\Checkout\Service\Easy\LanguageProvider;
use Nets\Checkout\Storefront\Controller\EmbeddedCheckoutController;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

class CheckoutConfirmPageSubscriber implements EventSubscriberInterface
{
    private ConfigService $configService;

    private CheckoutService $checkoutService;

    private RequestStack $requestStack;

    private LanguageProvider $languageProvider;

    private RouterInterface $router;

    private EasyApiService $apiService;

    public function __construct(
        ConfigService   $configService,
        CheckoutService $checkoutService,
        RequestStack    $requestStack,
        LanguageProvider $languageProvider,
        RouterInterface $router,
        EasyApiService $apiService
    ) {
        $this->configService = $configService;
        $this->checkoutService = $checkoutService;
        $this->requestStack = $requestStack;
        $this->languageProvider = $languageProvider;
        $this->router = $router;
        $this->apiService = $apiService;
    }

    /**
     * @return array|string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmLoaded',
        ];
    }

    public function onCheckoutConfirmLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $request = $event->getRequest();
        $paymentMethod = $salesChannelContext->getPaymentMethod();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $checkoutType = $this->configService->getCheckoutType($salesChannelId);

        // @TODO this should be merged with AsyncPaymentFinalizePay, refactor
        // @todo early return
        if ($paymentMethod->getHandlerIdentifier() === Checkout::class
            && CheckoutService::CHECKOUT_TYPE_EMBEDDED === $checkoutType) {
            try {
                $paymentId = $this->providePaymentId($request, $salesChannelContext, $event->getPage()->getCart());
            } catch (EasyApiException $ex) {
                if ($ex->getResponseErrors()) {
                    foreach ($ex->getResponseErrors() as $error) {
                        $this->requestStack->getCurrentRequest()->getSession()->getFlashBag()->add('danger', $error);
                    }
                }
                // we still want payment window to be showed
                $paymentId = null;
            }

            $environment = $this->configService->getEnvironment($salesChannelId);
            $easyCheckoutJsAsset = $environment == EasyApiService::ENV_LIVE
                ? CheckoutService::EASY_CHECKOUT_JS_ASSET_LIVE
                : CheckoutService::EASY_CHECKOUT_JS_ASSET_TEST;

            $templateVars = [
                'checkoutKey' => $this->configService->getCheckoutKey($salesChannelId),
                'paymentId' => $paymentId,
                'handlePaymentUrl' => $this->router->generate('frontend.nets.handle_payment'),
                'easyCheckoutJs' => $easyCheckoutJsAsset,
                'language' => $this->languageProvider->getLanguage($salesChannelContext->getContext()),
                'isEmbeddedCheckout' => $this->configService->getCheckoutType($salesChannelId) === 'embedded'
            ];

            $variablesStruct = new TransactionDetailsStruct();
            $variablesStruct->assign($templateVars);

            $event->getPage()->addExtension('easyCheckoutVariables', $variablesStruct);
        }
    }

    /**
     * @throws EasyApiException
     * @throws CartTotalUpdatedException
     */
    private function providePaymentId(
        Request $request,
        SalesChannelContext $salesChannelContext,
        Cart $cart
    ): string {
        $paymentId = $request->query->getAlnum('paymentId');

        if ($this->isPaymentValid($cart, $paymentId, $salesChannelContext->getSalesChannelId())) {
            return $paymentId;
        }

        $paymentId = $this->createNewPayment($salesChannelContext);
        $request->getSession()->set(EmbeddedCheckoutController::SESSION_NETS_PAYMENT_ID, $paymentId);

        return $paymentId;
    }

    /**
     * @throws EasyApiException
     */
    private function createNewPayment(SalesChannelContext $salesChannelContext): string
    {
        return json_decode($this->checkoutService->createPayment($salesChannelContext), true)['paymentId'];
    }

    /**
     * @throws EasyApiException
     * @throws CartTotalUpdatedException
     */
    private function isPaymentValid(Cart $cart, string $paymentId, string $salesChannelId): bool
    {
        if ($paymentId === '') {
            return false;
        }

        $payment = $this->apiService->getPayment($paymentId, $salesChannelId);

        if ($this->isCartTotalUpdated($cart, $payment->getPaymentId(), $salesChannelId)) {
            throw new CartTotalUpdatedException();
        }

        return true;
    }

    /**
     * @throws EasyApiException
     */
    private function isCartTotalUpdated(Cart $cart, string $paymentId, string $salesChannelId): bool
    {
        $payment = $this->apiService->getPayment($paymentId, $salesChannelId);

        return $cart->getPrice()->getTotalPrice() !== (float)$payment->getOrderAmount() / 100;
    }
}
