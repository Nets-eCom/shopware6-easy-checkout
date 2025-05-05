<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use NexiCheckout\Model\Request\Payment\Company;
use NexiCheckout\Model\Request\Payment\Consumer;
use NexiCheckout\Model\Request\Payment\PrivatePerson;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Order\OrderEntity;

class CustomerBuilder
{
    public function __construct(
        private readonly AddressBuilder $addressBuilder,
        private readonly PhoneNumberBuilder $phoneNumberBuilder
    ) {
    }

    public function createFromOrder(
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
            $this->addressBuilder->createFromOrderAddressEntity($orderShippingAddress),
            $this->addressBuilder->createFromOrderAddressEntity($order->getBillingAddress()),
            $this->phoneNumberBuilder->createFromOrderAddressEntity($order->getBillingAddress()),
            $isCompany ? null : new PrivatePerson($orderCustomer->getFirstName(), $orderCustomer->getLastName()),
            $isCompany ? new Company(
                $orderCustomer->getCompany(),
                $orderCustomer->getFirstName(),
                $orderCustomer->getLastName()
            ) : null,
        );
    }

    public function createFromCustomerEntity(CustomerEntity $customerEntity): Consumer
    {
        $isCompany = $this->isCompany($customerEntity->getCompany());

        $shippingAddress = $customerEntity->getDefaultShippingAddress();

        if (!$shippingAddress instanceof CustomerAddressEntity) {
            throw new AddressNotFoundException('');
        }

        return new Consumer(
            $customerEntity->getEmail(),
            $customerEntity->getCustomerNumber(),
            $this->addressBuilder->createFromCustomerAddressEntity($shippingAddress),
            $this->addressBuilder->createFromCustomerAddressEntity($customerEntity->getActiveBillingAddress()),
            $this->phoneNumberBuilder->createFromCustomerAddressEntity($customerEntity->getActiveBillingAddress()),
            $isCompany ? null : new PrivatePerson($customerEntity->getFirstName(), $customerEntity->getLastName()),
            $isCompany ? new Company(
                $customerEntity->getCompany(),
                $customerEntity->getFirstName(),
                $customerEntity->getLastName()
            ) : null,
        );
    }

    private function isCompany(?string $name): bool
    {
        return $name !== null && trim($name) !== '' && $name !== '0';
    }
}
