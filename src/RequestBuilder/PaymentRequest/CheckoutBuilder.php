<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use NexiCheckout\Model\Request\Payment\EmbeddedCheckout;
use NexiCheckout\Model\Request\Payment\HostedCheckout;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final readonly class CheckoutBuilder
{
    public function __construct(
        private ConfigurationProvider $configurationProvider,
        private CustomerBuilder $customerBuilder,
        private RouterInterface $router,
    ) {
    }

    public function createHosted(
        OrderEntity $order,
        string $returnUrl,
        string $salesChannelId,
    ): HostedCheckout {
        return new HostedCheckout(
            $returnUrl,
            $this->createCancelUrl(),
            $this->configurationProvider->getTermsUrl($salesChannelId),
            $this->configurationProvider->getMerchantTermsUrl($salesChannelId),
            $this->customerBuilder->createFromOrder($order),
            $this->configurationProvider->isAutoCharge($salesChannelId),
            true
        );
    }

    public function createEmbedded(SalesChannelContext $salesChannelContext): EmbeddedCheckout
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        return new EmbeddedCheckout(
            $this->createCheckoutUrl(),
            $this->configurationProvider->getTermsUrl($salesChannelId),
            $this->configurationProvider->getMerchantTermsUrl($salesChannelId),
            $this->customerBuilder->createFromCustomerEntity($salesChannelContext->getCustomer()),
            $this->configurationProvider->isAutoCharge($salesChannelId),
            true
        );
    }

    private function createCancelUrl(): string
    {
        return $this->router->generate('frontend.account.order.page', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function createCheckoutUrl(): string
    {
        return $this->router->generate('frontend.checkout.confirm.page', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
