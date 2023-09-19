<?php

namespace Nets\Checkout\Subscriber;

use Nets\Checkout\Service\ConfigService;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Nets\Checkout\Service\Easy\Api\TransactionDetailsStruct;
use Nets\Checkout\Service\Easy\CheckoutService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CheckoutConfirmPageSubscriber implements EventSubscriberInterface
{
    private ConfigService $configService;

    private CheckoutService $checkoutService;

    private RequestStack $requestStack;

    private EntityRepository $languageRepository;

    /**
     * CheckoutConfirmPageSubscriber constructor.
     */
    public function __construct(ConfigService $configService,
        CheckoutService $checkoutService,
        RequestStack $requestStack, EntityRepository $languageRepository)
    {
        $this->configService      = $configService;
        $this->checkoutService    = $checkoutService;
        $this->requestStack       = $requestStack;
        $this->languageRepository = $languageRepository;
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
        $checkoutType          = $this->configService->getCheckoutType($salesChannelContextId);

        if ($paymentMethod->getHandlerIdentifier() == 'Nets\Checkout\Service\Checkout'
            && $checkoutType == $this->checkoutService::CHECKOUT_TYPE_EMBEDDED) {
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

            $customerLanguage = $this->getCustomerLanguage($salesChannelContext->getContext());

            switch ($customerLanguage) {
                case 'de':
                    $checkoutLanguage = 'de-DE';

                    break;
                case 'da':
                    $checkoutLanguage = 'da-DK';

                    break;
                case 'sv':
                    $checkoutLanguage = 'sv-SE';

                    break;
                case 'nb':
                    $checkoutLanguage = 'nb-NO';

                    break;
                default:
                    $checkoutLanguage = 'en-GB';
            }

            $variablesStruct = new TransactionDetailsStruct();

            $easyCheckoutIsActive = $paymentMethod->getHandlerIdentifier() == 'Nets\Checkout\Service\Checkout' ? 1 : 0;

            $environment = $this->configService->getEnvironment($salesChannelContextId);

            $easyCheckoutJsAsset = $environment == 'test' ? $this->checkoutService::EASY_CHECKOUT_JS_ASSET_TEST :
                                             $this->checkoutService::EASY_CHECKOUT_JS_ASSET_LIVE;

            $templateVars = ['checkoutKey' => $this->configService->getCheckoutKey($salesChannelContextId),
                'environment'              => $environment,
                'paymentId'                => $paymentId,
                'checkoutType'             => $this->configService->getCheckoutType($salesChannelContextId),
                'easy_checkout_is_active'  => $easyCheckoutIsActive,
                'place_order_url'          => $event->getRequest()->getUriForPath('/nets/order/finish'),
                'easy_checkout_ja_asset'   => $easyCheckoutJsAsset,
                'language'                 => $checkoutLanguage,
            ];

            $variablesStruct->assign($templateVars);

            $event->getPage()->addExtension('easy_checkout_variables', $variablesStruct);
        }
    }

    private function getCustomerLanguage(Context $context): string
    {
        $languages = $context->getLanguageId();
        $criteria  = new Criteria([$languages]);
        $criteria->addAssociation('locale');

        /** @var null|LanguageEntity $language */
        $language = $this->languageRepository->search($criteria, $context)->first();

        if ($language === null || $language->getLocale() === null) {
            return 'en';
        }

        return substr($language->getLocale()->getCode(), 0, 2);
    }
}
