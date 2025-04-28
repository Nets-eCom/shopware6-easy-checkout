<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use NexiCheckout\Model\Request\Payment\PhoneNumber;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;

class PhoneNumberBuilder
{
    public function createFromOrderAddressEntity(
        OrderAddressEntity $address,
    ): ?PhoneNumber {
        $phoneNumber = $address->getPhoneNumber();

        if ($phoneNumber === null) {
            return null;
        }

        return $this->createPhoneNumber($phoneNumber, $address->getCountry()->getIso());
    }

    public function createFromCustomerAddressEntity(CustomerAddressEntity $address): ?PhoneNumber
    {
        $phoneNumber = $address->getPhoneNumber();

        if ($phoneNumber === null) {
            return null;
        }

        return $this->createPhoneNumber($phoneNumber, $address->getCountry()->getIso());
    }

    /**
     * @todo move to builder so it can be mocked
     */
    protected function getPhoneNumberUtils(): PhoneNumberUtil
    {
        return PhoneNumberUtil::getInstance();
    }

    private function createPhoneNumber(string $number, string $countryIso): ?PhoneNumber
    {
        $phoneUtil = $this->getPhoneNumberUtils();
        try {
            $phoneNumberObject = $phoneUtil->parse(
                $number,
                $countryIso
            );
        } catch (NumberParseException) {
            // @TODO log error to investigate issue
            return null;
        }

        return new PhoneNumber(
            '+' . $phoneNumberObject->getCountryCode(),
            $phoneNumberObject->getNationalNumber()
        );
    }
}
