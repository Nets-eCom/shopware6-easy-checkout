<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Subscriber;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Lifecycle\PaymentMethodsInstaller;
use Nexi\Checkout\PaymentMethods\PaymentMethodsFetcherInterface;
use Nexi\Checkout\Struct\SplitPaymentDetailsStruct;
use Nexi\Checkout\Subscriber\SplitPaymentCheckoutSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class SplitPaymentCheckoutSubscriberTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'sales-channel-id';

    private const CURRENCY_ISO_CODE = 'EUR';

    private const CREATE_EMBEDDED_URL = 'https://example.com/create-embedded';

    public function testItSubscribesToCheckoutConfirmPageLoadedEvent(): void
    {
        $sut = new SplitPaymentCheckoutSubscriber(
            $this->createStub(ConfigurationProvider::class),
            $this->createStub(PaymentMethodsFetcherInterface::class),
            $this->createStub(RouterInterface::class),
        );

        $this->assertSame(
            [
                CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmLoaded',
            ],
            $sut::getSubscribedEvents()
        );
    }

    public function testItDoesNothingWhenSplittingDisabled(): void
    {
        $configurationProvider = $this->createMock(ConfigurationProvider::class);
        $configurationProvider
            ->expects($this->once())
            ->method('isPayTypeSplitting')
            ->with(self::SALES_CHANNEL_ID)
            ->willReturn(false);

        $paymentMethodsFetcher = $this->createMock(PaymentMethodsFetcherInterface::class);
        $paymentMethodsFetcher->expects($this->never())->method('fetchAvailableMethodNames');

        $sut = new SplitPaymentCheckoutSubscriber(
            $configurationProvider,
            $paymentMethodsFetcher,
            $this->createStub(RouterInterface::class),
        );

        $page = new CheckoutConfirmPage();
        $event = $this->createEvent($page, PaymentMethodsInstaller::NEXI_CHECKOUT_EMBEDDED_TECHNICAL_NAME);

        $sut->onCheckoutConfirmLoaded($event);

        $this->assertNull($page->getExtension('nexiSplitPayment'));
    }

    public function testItDoesNothingWhenPaymentMethodIsNotNexiHostedOrEmbedded(): void
    {
        $configurationProvider = $this->createMock(ConfigurationProvider::class);
        $configurationProvider->method('isPayTypeSplitting')->willReturn(true);

        $paymentMethodsFetcher = $this->createMock(PaymentMethodsFetcherInterface::class);
        $paymentMethodsFetcher->expects($this->never())->method('fetchAvailableMethodNames');

        $sut = new SplitPaymentCheckoutSubscriber(
            $configurationProvider,
            $paymentMethodsFetcher,
            $this->createStub(RouterInterface::class),
        );

        $page = new CheckoutConfirmPage();
        $page->setPaymentMethods(new PaymentMethodCollection());

        $event = $this->createEvent($page, 'some_other_payment_method');

        $sut->onCheckoutConfirmLoaded($event);

        $this->assertNull($page->getExtension('nexiSplitPayment'));
    }

    public function testItAddsExtensionForEmbeddedPaymentMethodWithCreateUrl(): void
    {
        $configurationProvider = $this->createMock(ConfigurationProvider::class);
        $configurationProvider->method('isPayTypeSplitting')->willReturn(true);
        $configurationProvider->method('getPayTypeOptions')->with(self::SALES_CHANNEL_ID)->willReturn([
            [
                'name' => 'Card',
                'enabled' => true,
            ],
            [
                'name' => 'Swish',
                'enabled' => true,
            ],
        ]);

        $paymentMethodsFetcher = $this->createMock(PaymentMethodsFetcherInterface::class);
        $paymentMethodsFetcher
            ->method('fetchAvailableMethodNames')
            ->with(self::SALES_CHANNEL_ID, self::CURRENCY_ISO_CODE)
            ->willReturn(['Card', 'Swish']);

        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('payment.nexicheckout.embedded.create-embedded', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn(self::CREATE_EMBEDDED_URL);

        $sut = new SplitPaymentCheckoutSubscriber($configurationProvider, $paymentMethodsFetcher, $router);

        $embeddedMethod = new PaymentMethodEntity();
        $embeddedMethod->setId('nexi-embedded-id');
        $embeddedMethod->setTechnicalName(PaymentMethodsInstaller::NEXI_CHECKOUT_EMBEDDED_TECHNICAL_NAME);

        $page = new CheckoutConfirmPage();
        $page->setPaymentMethods(new PaymentMethodCollection([$embeddedMethod]));

        $event = $this->createEvent($page, PaymentMethodsInstaller::NEXI_CHECKOUT_EMBEDDED_TECHNICAL_NAME);

        $sut->onCheckoutConfirmLoaded($event);

        /** @var SplitPaymentDetailsStruct|null $extension */
        $extension = $page->getExtension('nexiSplitPayment');
        $this->assertInstanceOf(SplitPaymentDetailsStruct::class, $extension);
        $this->assertSame(self::CREATE_EMBEDDED_URL, $extension->getCreateEmbeddedPaymentUrl());
        $this->assertSame(
            [
                [
                    'value' => 'Card',
                    'label' => 'Card',
                ],
                [
                    'value' => 'Swish',
                    'label' => 'Swish',
                ],
            ],
            $extension->getSubselections()
        );
    }

    public function testItFiltersOutDisabledOptions(): void
    {
        $configurationProvider = $this->createMock(ConfigurationProvider::class);
        $configurationProvider->method('isPayTypeSplitting')->willReturn(true);
        $configurationProvider->method('getPayTypeOptions')->willReturn([
            [
                'name' => 'Card',
                'enabled' => true,
            ],
            [
                'name' => 'Swish',
                'enabled' => false,
            ],
            [
                'name' => 'PayPal',
                'enabled' => true,
            ],
        ]);

        $paymentMethodsFetcher = $this->createMock(PaymentMethodsFetcherInterface::class);
        $paymentMethodsFetcher->method('fetchAvailableMethodNames')->willReturn(['Card', 'Swish', 'PayPal']);

        $sut = new SplitPaymentCheckoutSubscriber(
            $configurationProvider,
            $paymentMethodsFetcher,
            $this->createStub(RouterInterface::class),
        );

        $page = new CheckoutConfirmPage();
        $page->setPaymentMethods(new PaymentMethodCollection());

        $event = $this->createEvent($page, PaymentMethodsInstaller::NEXI_CHECKOUT_HOSTED_TECHNICAL_NAME);

        $sut->onCheckoutConfirmLoaded($event);

        /** @var SplitPaymentDetailsStruct $extension */
        $extension = $page->getExtension('nexiSplitPayment');
        $this->assertNull($extension->getCreateEmbeddedPaymentUrl());
        $this->assertSame(
            [
                [
                    'value' => 'Card',
                    'label' => 'Card',
                ],
                [
                    'value' => 'PayPal',
                    'label' => 'PayPal',
                ],
            ],
            $extension->getSubselections()
        );
    }

    private function createEvent(CheckoutConfirmPage $page, string $paymentMethodTechnicalName): CheckoutConfirmPageLoadedEvent
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode(self::CURRENCY_ISO_CODE);

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setTechnicalName($paymentMethodTechnicalName);

        $salesChannelContext = $this->createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);
        $salesChannelContext->method('getCurrency')->willReturn($currency);
        $salesChannelContext->method('getPaymentMethod')->willReturn($paymentMethod);

        return new CheckoutConfirmPageLoadedEvent($page, $salesChannelContext, new Request());
    }
}
