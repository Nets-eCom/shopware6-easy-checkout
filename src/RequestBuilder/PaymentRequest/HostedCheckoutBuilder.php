<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use NexiCheckout\Model\Request\Payment\HostedCheckout;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final readonly class HostedCheckoutBuilder implements CheckoutBuilderInterface
{
    public function __construct(
        private ConfigurationProvider $configurationProvider,
        private CustomerBuilder $customerBuilder,
        private RouterInterface $router,
    ) {
    }

    public function create(
        OrderEntity $order,
        string $returnUrl,
        string $salesChannelId,
    ): HostedCheckout {
        return new HostedCheckout(
            $returnUrl,
            $this->createCancelUrl(),
            $this->configurationProvider->getTermsUrl($salesChannelId),
            $this->configurationProvider->getMerchantTermsUrl($salesChannelId),
            $this->customerBuilder->create($order),
            $this->configurationProvider->isAutoCharge($salesChannelId),
            true
        );
    }

    private function createCancelUrl(): string
    {
        return $this->router->generate('frontend.account.order.page', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
