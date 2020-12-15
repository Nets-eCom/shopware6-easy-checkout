<?php

namespace Nets\Checkout\Subscriber;

use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Nets\Checkout\Service\Easy\Api\TransactionDetailsStruct;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Nets\Checkout\Service\ConfigService;
use Nets\Checkout\Service\Easy\CheckoutService;
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
     * CheckoutConfirmPageSubscriber constructor.
     * @param ConfigService $configService
     * @param CheckoutService $checkoutService
     */
    public function __construct(ConfigService $configService, CheckoutService $checkoutService)
    {
        $this->configService = $configService;
        $this->checkoutService = $checkoutService;
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

                if ($paymentMethod->getHandlerIdentifier() == 'Nets\Checkout\Service\Checkout' &&
                    $checkoutType == $this->checkoutService::CHECKOUT_TYPE_EMBEDDED) {
                    //exit;

                    $paymentId = json_decode($this->checkoutService->createPayment($salesChannelContext, $this->checkoutService::CHECKOUT_TYPE_EMBEDDED, null), true);
                    $paymentId = $paymentId['paymentId'];
                    $page = $event->getPage();
                    $variablesStruct = new TransactionDetailsStruct();

                    $easyCheckoutIsActive = $paymentMethod->getHandlerIdentifier() == 'Nets\Checkout\Service\Checkout' ? 1 : 0;

                    $templateVars = ['checkoutKey' => $this->configService->getCheckoutKey($salesChannelContextId),
                        'environment' => $this->configService->getEnvironment($salesChannelContextId),
                        'paymentId' => $paymentId,
                        'checkoutType' => $this->configService->getCheckoutType($salesChannelContextId),
                        'easy_checkout_is_active' => $easyCheckoutIsActive];

                    $variablesStruct->assign($templateVars);

                    $page->addExtension('easy_checkout_variables', $variablesStruct);

                }
            } catch (\Exception $ex) {
                // handle exception ....
            }
    }
}
