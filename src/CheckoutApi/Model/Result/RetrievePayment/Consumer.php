<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment;

use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Consumer\Address;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Consumer\Company;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Consumer\PrivatePerson;

readonly class Consumer
{
    public function __construct(
        private Address $shippingAddress,
        private Address $billingAddress,
        private PrivatePerson $privatePerson,
        private Company $company,
    ) {
    }

    public function getShippingAddress(): Address
    {
        return $this->shippingAddress;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getPrivatePerson(): PrivatePerson
    {
        return $this->privatePerson;
    }

    public function getBillingAddress(): Address
    {
        return $this->billingAddress;
    }
}
