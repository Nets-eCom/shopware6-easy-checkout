<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder\PaymentRequest;

use NexiNets\CheckoutApi\Model\Request\Payment\Company;
use NexiNets\CheckoutApi\Model\Request\Payment\Consumer;
use NexiNets\CheckoutApi\Model\Request\Payment\PrivatePerson;
use Shopware\Core\Checkout\Customer\CustomerEntity;

class CustomerBuilder
{
    public function __construct(
        private readonly AddressBuilder $addressBuilder,
        private readonly PhoneNumberBuilder $phoneNumberBuilder
    ) {
    }

    public function create(
        CustomerEntity $customer
    ): Consumer {
        $isCompany = $this->isCompany($customer->getActiveBillingAddress()->getCompany());

        return new Consumer(
            $customer->getEmail(),
            null,
            $this->addressBuilder->create($customer->getActiveShippingAddress()),
            null,
            $this->phoneNumberBuilder->create($customer->getActiveShippingAddress()),
            $isCompany ? null : new PrivatePerson($customer->getFirstName(), $customer->getLastName()),
            $isCompany ? new Company(
                $customer->getActiveBillingAddress()->getCompany(),
                $customer->getFirstName(),
                $customer->getLastName()
            ) : null,
        );
    }

    private function isCompany(?string $name): bool
    {
        return $name !== null && $name !== '' && $name !== '0';
    }
}
