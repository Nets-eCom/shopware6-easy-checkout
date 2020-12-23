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
            try {
                $salesChannelContext = $event->getSalesChannelContext();
                $paymentMethod = $salesChannelContext->getPaymentMethod();
                $salesChannelContextId = $salesChannelContext->getSalesChannel()->getId();
                $checkoutType = $this->configService->getCheckoutType($salesChannelContextId);

                $page = $event->getPage();
                if ($paymentMethod->getHandlerIdentifier() == 'Nets\Checkout\Service\Checkout' &&
                    $checkoutType == $this->checkoutService::CHECKOUT_TYPE_EMBEDDED) {
                    $paymentId = json_decode($this->checkoutService->createPayment($salesChannelContext), true);
                    $paymentId = $paymentId['paymentId'];

                    $variablesStruct = new TransactionDetailsStruct();
                    $easyCheckoutIsActive = $paymentMethod->getHandlerIdentifier() == 'Nets\Checkout\Service\Checkout' ? 1 : 0;
                    $errors = $page->getCart()->getErrors();
                    $templateVars = ['checkoutKey' => $this->configService->getCheckoutKey($salesChannelContextId),
                        'environment' => $this->configService->getEnvironment($salesChannelContextId),
                        'paymentId' => $paymentId,
                        'checkoutType' => $this->configService->getCheckoutType($salesChannelContextId),
                        'easy_checkout_is_active' => $easyCheckoutIsActive,
                        'cart_errors' => $errors->count()];

                    $variablesStruct->assign($templateVars);
                    $page->addExtension('easy_checkout_variables', $variablesStruct);
                }

            } catch (EasyApiException $ex) {
                if($ex->getResponseErrors()) {
                    $this->session->getFlashBag()->add('danger', 'Error during Easy checkout initialization :');
                    foreach ($ex->getResponseErrors() as $error ) {
                        $this->session->getFlashBag()->add('danger', $error);
                    }
                }
           }
    }
}
