<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment\Consumer;

use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Company\ContactDetails;

readonly class Company
{
    public function __construct(
        private ?string $merchantReference,
        private ?string $name,
        private ?string $registrationNumber,
        private ?ContactDetails $contactDetails,
    ) {
    }

    public function getMerchantReference(): ?string
    {
        return $this->merchantReference;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function getContactDetails(): ?ContactDetails
    {
        return $this->contactDetails;
    }
}
