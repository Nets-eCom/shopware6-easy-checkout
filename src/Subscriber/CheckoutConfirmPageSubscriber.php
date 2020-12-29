<?php

namespace Nets\Checkout\Subscriber;

use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Nets\Checkout\Service\Easy\Api\TransactionDetailsStruct;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Nets\Checkout\Service\ConfigService;
use Nets\Checkout\Service\Easy\CheckoutService;
use Symfony\Component\HttpFoundation\Session\Session;

class CheckoutConfirmPageSubscriber implements EventSubscriberInterface
{

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var CheckoutService
     */
    private $checkoutService;

    /**
     * @var Session
     */
    private $session;

    /**
     * CheckoutConfirmPageSubscriber constructor.
     * @param ConfigService $configService
     * @param CheckoutService $checkoutService
     * @param Session $session
     */
    public function __construct(ConfigService $configService,
                                CheckoutService $checkoutService,
                                Session $session)
    {
        $this->configService = $configService;
        $this->checkoutService = $checkoutService;
        $this->session = $session;
    }

    /**
     * @return array|string[]
     */
    public static function getSubscribedEvents()
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmLoaded'
        ];
    }

    /**
     * @param CheckoutConfirmPageLoadedEvent $event
     * @throws EasyApiException
     */
    public function onCheckoutConfirmLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();

        $paymentMethod = $salesChannelContext->getPaymentMethod();

        $salesChannelContextId = $salesChannelContext->getSalesChannel()->getId();

        $checkoutType = $this->configService->getCheckoutType($salesChannelContextId);

        if ($paymentMethod->getHandlerIdentifier() == 'Nets\Checkout\Service\Checkout' &&
            $checkoutType == $this->checkoutService::CHECKOUT_TYPE_EMBEDDED) {

            try {
                $paymentId = json_decode($this->checkoutService->createPayment($salesChannelContext), true);
                $paymentId = $paymentId['paymentId'];

            } catch (EasyApiException $ex) {
                if($ex->getResponseErrors()) {
                    $this->session->getFlashBag()->add('danger', 'Error during Easy checkout initialization :');
                    foreach ($ex->getResponseErrors() as $error ) {
                        $this->session->getFlashBag()->add('danger', $error);
                    }
                }
                // we still want payment window to be showed
                $paymentId = null;
            }

            $variablesStruct = new TransactionDetailsStruct();

            $easyCheckoutIsActive = $paymentMethod->getHandlerIdentifier() == 'Nets\Checkout\Service\Checkout' ? 1 : 0;

            $environment = $this->configService->getEnvironment($salesChannelContextId);

            $easyCheckoutJsAsset = 'test' == $environment ? $this->checkoutService::EASY_CHECKOUT_JS_ASSET_TEST :
                                             $this->checkoutService::EASY_CHECKOUT_JS_ASSET_LIVE;

            $templateVars = ['checkoutKey' => $this->configService->getCheckoutKey($salesChannelContextId),
                'environment' => $environment,
                'paymentId' => $paymentId,
                'checkoutType' => $this->configService->getCheckoutType($salesChannelContextId),
                'easy_checkout_is_active' => $easyCheckoutIsActive,
                'place_order_url' => $event->getRequest()->getUriForPath('/nets/order/finish'),
                'easy_checkout_ja_asset' => $easyCheckoutJsAsset];

            $variablesStruct->assign($templateVars);

            $event->getPage()->addExtension('easy_checkout_variables', $variablesStruct);
        }
    }
}
