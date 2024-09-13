<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment\Consumer;

use NexiNets\CheckoutApi\Model\Result\Shared\PhoneNumber;

class PrivatePerson
{
    public function __construct(
        private readonly ?string $merchantReference,
        private readonly ?\DateTimeInterface $dataOfBirth,
        private readonly ?string $firstName,
        private readonly ?string $lastName,
        private readonly ?string $email,
        private readonly ?PhoneNumber $phoneNumber
    ) {
    }

    public function getMerchantReference(): ?string
    {
        return $this->merchantReference;
    }

    public function getDataOfBirth(): ?\DateTimeInterface
    {
        return $this->dataOfBirth;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhoneNumber(): ?PhoneNumber
    {
        return $this->phoneNumber;
    }
}
