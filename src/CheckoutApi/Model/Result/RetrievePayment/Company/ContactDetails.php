<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment\Company;

use NexiNets\CheckoutApi\Model\Result\PhoneNumber;

readonly class ContactDetails
{
    public function __construct(
        private ?string $firstName,
        private ?string $lastName,
        private ?string $email,
        private ?PhoneNumber $phoneNumber
    ) {
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
