<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder\PaymentRequest;

use NexiNets\CheckoutApi\Model\Request\Payment\HostedCheckout;
use NexiNets\Configuration\ConfigurationProvider;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext,
    ): HostedCheckout {
        return new HostedCheckout(
            $transaction->getReturnUrl(),
            $this->createCancelUrl(),
            $this->configurationProvider->getTermsUrl($salesChannelContext->getSalesChannelId()),
            $this->configurationProvider->getMerchantTermsUrl($salesChannelContext->getSalesChannelId()),
            $this->customerBuilder->create($salesChannelContext),
            $this->configurationProvider->isAutoCharge($salesChannelContext->getSalesChannelId()),
            true
        );
    }

    private function createCancelUrl(): string
    {
        return $this->router->generate('frontend.account.order.page', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
