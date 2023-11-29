<?php

namespace Nets\Checkout\Subscriber;

use Nets\Checkout\Service\Checkout;
use Nets\Checkout\Service\Easy\ConfigService;
use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Nets\Checkout\Service\Easy\Api\TransactionDetailsStruct;
use Nets\Checkout\Service\Easy\CheckoutService;
use Nets\Checkout\Service\Easy\LanguageProvider;
use Shopware\Storefront\Framework\Routing\Router;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CheckoutConfirmPageSubscriber implements EventSubscriberInterface
{
    private ConfigService $configService;

    private CheckoutService $checkoutService;

    private RequestStack $requestStack;

    private LanguageProvider $languageProvider;

    private Router $router;

    /**
     * CheckoutConfirmPageSubscriber constructor.
     */
    public function __construct(
        ConfigService   $configService,
        CheckoutService $checkoutService,
        RequestStack    $requestStack,
        LanguageProvider $languageProvider,
        Router $router
    ) {
        $this->configService = $configService;
        $this->checkoutService = $checkoutService;
        $this->requestStack = $requestStack;
        $this->languageProvider = $languageProvider;
        $this->router = $router;
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
        $salesChannelContext   = $event->getSalesChannelContext();
        $paymentMethod         = $salesChannelContext->getPaymentMethod();
        $salesChannelContextId = $salesChannelContext->getSalesChannel()->getId();
        $checkoutType          = $this->configService->getCheckoutType();

        // @TODO this should be merged with AsyncPaymentFinalizePay, refactor
        // @todo early return
        if ($paymentMethod->getHandlerIdentifier() === Checkout::class
            && CheckoutService::CHECKOUT_TYPE_EMBEDDED === $checkoutType) {
            try {
                $paymentId = json_decode($this->checkoutService->createPayment($salesChannelContext), true);
                $paymentId = $paymentId['paymentId'];
            } catch (EasyApiException $ex) {
                if ($ex->getResponseErrors()) {
                    foreach ($ex->getResponseErrors() as $error) {
                        $this->requestStack->getCurrentRequest()->getSession()->getFlashBag()->add('danger', $error);
                    }
                }
                // we still want payment window to be showed
                $paymentId = null;
            }

            $environment = $this->configService->getEnvironment();
            $easyCheckoutJsAsset = $environment == EasyApiService::ENV_LIVE
                ? CheckoutService::EASY_CHECKOUT_JS_ASSET_LIVE
                : CheckoutService::EASY_CHECKOUT_JS_ASSET_TEST;

            $templateVars = [
                'checkoutKey' => $this->configService->getCheckoutKey(),
                'paymentId' => $paymentId,
                'checkoutType' => $this->configService->getCheckoutType(),
                'easy_checkout_is_active' => 1,
                'place_order_url' => $this->router->generate('nets.finish.order.controller'),
                'easy_checkout_js_asset' => $easyCheckoutJsAsset,
                'language' => $this->languageProvider->getLanguage($salesChannelContext->getContext()),
            ];

            $variablesStruct = new TransactionDetailsStruct();
            $variablesStruct->assign($templateVars);

            $event->getPage()->addExtension('easy_checkout_variables', $variablesStruct);
        }
    }
}
