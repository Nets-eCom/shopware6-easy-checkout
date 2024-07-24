<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder\PaymentRequest;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use NexiNets\CheckoutApi\Model\Request\Payment\PhoneNumber;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;

class PhoneNumberBuilder
{
    public function create(
        CustomerAddressEntity $customerAddress,
    ): ?PhoneNumber {
        $phoneNumber = $customerAddress->getPhoneNumber();

        if ($phoneNumber === null) {
            return null;
        }

        $phoneUtil = $this->getPhoneNumberUtils();
        try {
            $phoneNumberObject = $phoneUtil->parse(
                $phoneNumber,
                $customerAddress->getCountry()->getIso()
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

    /**
     * @todo move to builder so it can be mocked
     */
    protected function getPhoneNumberUtils(): PhoneNumberUtil
    {
        return PhoneNumberUtil::getInstance();
    }
}
