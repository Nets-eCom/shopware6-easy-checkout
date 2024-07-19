<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\Payment;

class HostedCheckout extends Checkout
{
    public function __construct(
        protected readonly string $returnUrl,
        protected readonly string $cancelUrl,
        protected string $termsUrl,
        protected ?string $merchantTermsUrl = null,
        protected ?Consumer $consumer = null,
        protected ?bool $isAutoCharge = null,
        protected ?bool $merchantHandlesConsumerData = null,
        protected array $shippingCountries = [],
        protected ?Shipping $shipping = null,
        protected ?ConsumerType $consumerType = null,
        protected ?bool $isPublicDevice = null,
        protected ?Appearance $appearance = null,
        protected ?string $countryCode = null,
    ) {
        parent::__construct(
            $this->termsUrl,
            $this->merchantTermsUrl,
            $consumer,
            $isAutoCharge,
            $merchantHandlesConsumerData,
            $shippingCountries,
            $shipping,
            $consumerType,
            $isPublicDevice,
            $appearance,
            $countryCode
        );
    }

    public function jsonSerialize(): array
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'integrationType' => IntegrationTypeEnum::HostedPaymentPage->name,
                'returnUrl' => $this->returnUrl,
                'cancelUrl' => $this->cancelUrl,
            ]
        );
    }
}
