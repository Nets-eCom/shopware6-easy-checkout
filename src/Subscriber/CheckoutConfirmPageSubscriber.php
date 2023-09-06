<?php

namespace Nets\Checkout\Subscriber;

use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Nets\Checkout\Service\Easy\Api\TransactionDetailsStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Nets\Checkout\Service\ConfigService;
use Nets\Checkout\Service\Easy\CheckoutService;
use Symfony\Component\HttpFoundation\Session\Session;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

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
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * CheckoutConfirmPageSubscriber constructor.
     * @param ConfigService $configService
     * @param CheckoutService $checkoutService
     * @param Session $session
     * @param EntityRepositoryInterface $languageRepository
     */
    public function __construct(ConfigService $configService,
                                CheckoutService $checkoutService,
                                Session $session, EntityRepositoryInterface $languageRepository)
    {
        $this->configService = $configService;
        $this->checkoutService = $checkoutService;
        $this->session = $session;
        $this->languageRepository = $languageRepository;
    }

    /**
     * @return array|string[]
     */
    public static function getSubscribedEvents() : array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmLoaded'
        ];
    }

    /**
     * @param CheckoutConfirmPageLoadedEvent $event
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
                    foreach ($ex->getResponseErrors() as $error ) {
                        $this->session->getFlashBag()->add('danger', $error);
                    }
                }
                // we still want payment window to be showed
                $paymentId = null;
            }

            $customerLanguage = $this->getCustomerLanguage($salesChannelContext->getContext());

            switch($customerLanguage) {
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

            $easyCheckoutJsAsset = 'test' == $environment ? $this->checkoutService::EASY_CHECKOUT_JS_ASSET_TEST :
                                             $this->checkoutService::EASY_CHECKOUT_JS_ASSET_LIVE;

            $templateVars = ['checkoutKey' => $this->configService->getCheckoutKey($salesChannelContextId),
                'environment' => $environment,
                'paymentId' => $paymentId,
                'checkoutType' => $this->configService->getCheckoutType($salesChannelContextId),
                'easy_checkout_is_active' => $easyCheckoutIsActive,
                'place_order_url' => $event->getRequest()->getUriForPath('/nets/order/finish'),
                'easy_checkout_ja_asset' => $easyCheckoutJsAsset,
				'language' => $checkoutLanguage

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

        if (null === $language || null === $language->getLocale()) {
            return 'en';
        }

        return substr($language->getLocale()->getCode(), 0, 2);
    }
}
