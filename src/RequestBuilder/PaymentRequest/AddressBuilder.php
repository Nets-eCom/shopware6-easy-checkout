<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use NexiCheckout\Model\Request\Payment\Address;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;

class AddressBuilder
{
    public function createFromOrderAddressEntity(
        OrderAddressEntity $address
    ): Address {
        return new Address(
            $address->getStreet(),
            $address->getAdditionalAddressLine1(),
            $address->getZipcode(),
            $address->getCity(),
            $address->getCountry()->getIso3(),
        );
    }

    public function createFromCustomerAddressEntity(CustomerAddressEntity $address): Address
    {
        return new Address(
            $address->getStreet(),
            $address->getAdditionalAddressLine1(),
            $address->getZipcode(),
            $address->getCity(),
            $address->getCountry()->getIso3(),
        );
    }
}
