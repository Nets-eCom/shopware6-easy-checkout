<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use NexiCheckout\Model\Request\Payment\IntegrationTypeEnum;
use Symfony\Component\Routing\RouterInterface;

class CheckoutBuilderFactory
{
    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly CustomerBuilder $customerBuilder,
        private readonly RouterInterface $router
    ) {
    }

    public function build(IntegrationTypeEnum $type): CheckoutBuilderInterface
    {
        return match ($type) {
            IntegrationTypeEnum::HostedPaymentPage => new HostedCheckoutBuilder(
                $this->configurationProvider,
                $this->customerBuilder,
                $this->router,
            ),
            IntegrationTypeEnum::EmbeddedCheckout => throw new \Exception('To be implemented'),
        };
    }
}
