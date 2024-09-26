<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook\Data\Consumer;

readonly class Address
{
    public function __construct(
        private string $addressLine1,
        private string $addressLine2,
        private string $city,
        private string $country,
        private string $postcode,
        private string $receiverLine
    ) {
    }

    public function getAddressLine1(): string
    {
        return $this->addressLine1;
    }

    public function getAddressLine2(): string
    {
        return $this->addressLine2;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getPostcode(): string
    {
        return $this->postcode;
    }

    public function getReceiverLine(): string
    {
        return $this->receiverLine;
    }
}
