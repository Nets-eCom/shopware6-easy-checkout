<?php

declare(strict_types=1);

namespace Nexi\Checkout\Subscriber;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Lifecycle\PaymentMethodsInstaller;
use Nexi\Checkout\PaymentMethods\PaymentMethodsFetcherInterface;
use Nexi\Checkout\Struct\SplitPaymentDetailsStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class SplitPaymentCheckoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly PaymentMethodsFetcherInterface $paymentMethodsFetcher,
        private readonly RouterInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmLoaded',
        ];
    }

    public function onCheckoutConfirmLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        if (!$this->configurationProvider->isPayTypeSplitting($salesChannelId)) {
            return;
        }

        $page = $event->getPage();
        $paymentMethods = $page->getPaymentMethods();

        $currentTechnicalName = $salesChannelContext->getPaymentMethod()->getTechnicalName();
        $isHostedCurrent = $currentTechnicalName === PaymentMethodsInstaller::NEXI_CHECKOUT_HOSTED_TECHNICAL_NAME;

        $hasEmbedded = false;
        foreach ($paymentMethods as $method) {
            if ($method->getTechnicalName() === PaymentMethodsInstaller::NEXI_CHECKOUT_EMBEDDED_TECHNICAL_NAME) {
                $hasEmbedded = true;
                break;
            }
        }

        if (!$hasEmbedded && !$isHostedCurrent) {
            return;
        }

        if ($hasEmbedded) {
            $page->setPaymentMethods(
                $paymentMethods->filter(
                    fn ($m) => $m->getTechnicalName() !== PaymentMethodsInstaller::NEXI_CHECKOUT_EMBEDDED_TECHNICAL_NAME
                )
            );

            $page->addExtension(
                'nexiSplitPayment',
                new SplitPaymentDetailsStruct(
                    $this->buildSubselections($salesChannelContext),
                    $this->router->generate(
                        'payment.nexicheckout.embedded.create-embedded',
                        [],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                )
            );

            return;
        }

        $page->addExtension(
            'nexiSplitPayment',
            new SplitPaymentDetailsStruct(
                $this->buildSubselections($salesChannelContext),
                null,
            )
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function buildSubselections(SalesChannelContext $salesChannelContext): array
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $currencyIsoCode = $salesChannelContext->getCurrency()->getIsoCode();

        $configuredOptions = $this->configurationProvider->getPayTypeOptions($salesChannelId);

        $availableNames = $this->paymentMethodsFetcher->fetchAvailableMethodNames($salesChannelId, $currencyIsoCode);
        $availableSet = array_flip($availableNames);

        $subselections = [];
        foreach ($configuredOptions as $option) {
            if (empty($option['enabled'])) {
                continue;
            }

            $name = $option['name'] ?? '';
            if ($name !== '' && isset($availableSet[$name])) {
                $subselections[] = [
                    'value' => $name,
                    'label' => $name,
                ];
            }
        }

        return $subselections;
    }
}
