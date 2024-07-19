<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder\PaymentRequest;

use NexiNets\CheckoutApi\Model\Request\Payment\Address;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;

class AddressBuilder
{
    public function create(
        CustomerAddressEntity $address
    ): Address {
        return new Address(
            $address->getStreet(),
            $address->getAdditionalAddressLine1(),
            $address->getZipcode(),
            $address->getCity(),
            $address->getCountry()->getIso3(),
        );
    }
}
