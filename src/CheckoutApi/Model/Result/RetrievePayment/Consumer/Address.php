<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment\Consumer;

use NexiNets\CheckoutApi\Model\Result\Shared\PhoneNumber;

readonly class Address
{
    public function __construct(
        private ?string $addressLine1,
        private ?string $addressLine2,
        private ?string $receiverLine,
        private ?string $postalCode,
        private ?string $city,
        private ?PhoneNumber $phoneNumber = null
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

    public function getReceiverLine(): string
    {
        return $this->receiverLine;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getPhoneNumber(): ?PhoneNumber
    {
        return $this->phoneNumber;
    }
}
