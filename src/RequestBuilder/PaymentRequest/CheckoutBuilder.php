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
    public const CANCEL_PARAMETER_NAME = 'cancel';

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
            $this->createCancelUrl($returnUrl),
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

    private function createCancelUrl(string $returnUrl): string
    {
        return \sprintf('%s&%s=1', $returnUrl, self::CANCEL_PARAMETER_NAME);
    }

    private function createCheckoutUrl(): string
    {
        return $this->router->generate('nexicheckout_payment.nexicheckout.embedded.confirm', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
