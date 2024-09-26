<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook\Data;

use NexiNets\CheckoutApi\Model\Webhook\Data\Consumer\Address;
use NexiNets\CheckoutApi\Model\Webhook\Data\Consumer\PhoneNumber;

readonly class Consumer
{
    public function __construct(
        private string $firstName,
        private string $lastName,
        private Address $billingAddress,
        private string $country,
        private string $email,
        private string $ip,
        private PhoneNumber $phoneNumber,
        private Address $shippingAddress
    ) {
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getBillingAddress(): Address
    {
        return $this->billingAddress;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getPhoneNumber(): PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function getShippingAddress(): Address
    {
        return $this->shippingAddress;
    }
}
