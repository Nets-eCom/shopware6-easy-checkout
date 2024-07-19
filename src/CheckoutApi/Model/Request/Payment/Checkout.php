<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\Payment;

abstract class Checkout implements \JsonSerializable
{
    /**
     * @param list<string> $shippingCountries
     */
    public function __construct(
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
    }

    /**
     * @return array{
     *     termsUrl: string,
     *     consumer: ?Consumer,
     *     merchantTermsUrl: ?string,
     *     shippingCountries: list<string>,
     *     shipping: ?Shipping,
     *     consumerType: ?ConsumerType,
     *     charge: ?bool,
     *     publicDevice: ?bool,
     *     merchantHandlesConsumerData: ?bool,
     *     appearance: ?Appearance,
     *     countryCode: ?string
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'termsUrl' => $this->termsUrl,
            'consumer' => $this->consumer,
            'charge' => $this->isAutoCharge,
            'merchantHandlesConsumerData' => $this->merchantHandlesConsumerData,
            'merchantTermsUrl' => $this->merchantTermsUrl,
            'shippingCountries' => $this->shippingCountries,
            'shipping' => $this->shipping,
            'consumerType' => $this->consumerType,
            'publicDevice' => $this->isPublicDevice,
            'appearance' => $this->appearance,
            'countryCode' => $this->countryCode,
        ];
    }
}
