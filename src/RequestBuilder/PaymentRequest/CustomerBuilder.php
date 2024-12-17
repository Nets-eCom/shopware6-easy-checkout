<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder\PaymentRequest;

use NexiNets\CheckoutApi\Model\Request\Payment\Company;
use NexiNets\CheckoutApi\Model\Request\Payment\Consumer;
use NexiNets\CheckoutApi\Model\Request\Payment\PrivatePerson;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Order\OrderEntity;

class CustomerBuilder
{
    public function __construct(
        private readonly AddressBuilder $addressBuilder,
        private readonly PhoneNumberBuilder $phoneNumberBuilder
    ) {
    }

    public function create(
        OrderEntity $order
    ): Consumer {
        $orderCustomer = $order->getOrderCustomer();
        $isCompany = $this->isCompany($orderCustomer->getCompany());

        $orderShippingAddress = $order->getDeliveries()?->first()?->getShippingOrderAddress();
        if ($orderShippingAddress === null) {
            throw new AddressNotFoundException($order->getDeliveries()?->first()?->getShippingOrderAddressId() ?? '');
        }

        return new Consumer(
            $orderCustomer->getEmail(),
            $orderCustomer->getCustomerNumber(),
            $this->addressBuilder->create($orderShippingAddress),
            $this->addressBuilder->create($order->getBillingAddress()),
            $this->phoneNumberBuilder->create($order->getBillingAddress()),
            $isCompany ? null : new PrivatePerson($orderCustomer->getFirstName(), $orderCustomer->getLastName()),
            $isCompany ? new Company(
                $orderCustomer->getCompany(),
                $orderCustomer->getFirstName(),
                $orderCustomer->getLastName()
            ) : null,
        );
    }

    private function isCompany(?string $name): bool
    {
        return $name !== null && trim($name) !== '' && $name !== '0';
    }
}
