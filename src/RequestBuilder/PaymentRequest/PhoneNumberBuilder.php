<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder\PaymentRequest;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use NexiNets\CheckoutApi\Model\Request\Payment\PhoneNumber;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PhoneNumberBuilder
{
    public function create(
        SalesChannelContext $salesChannelContext
    ): ?PhoneNumber {
        $phoneNumber = $salesChannelContext->getCustomer()
            ->getActiveShippingAddress()
            ->getPhoneNumber();

        if ($phoneNumber === null) {
            return null;
        }

        $phoneUtil = $this->getPhoneNumberUtils();
        try {
            $phoneNumberObject = $phoneUtil->parse(
                $phoneNumber,
                $salesChannelContext->getCustomer()->getActiveShippingAddress()->getCountry()->getIso()
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
