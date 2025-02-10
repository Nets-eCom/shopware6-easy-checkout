<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use NexiCheckout\Model\Request\Payment\Address;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;

class AddressBuilder
{
    public function create(
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
}
