<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use NexiCheckout\Model\Request\Payment\PhoneNumber;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;

class PhoneNumberBuilder
{
    public function create(
        OrderAddressEntity $customerAddress,
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
