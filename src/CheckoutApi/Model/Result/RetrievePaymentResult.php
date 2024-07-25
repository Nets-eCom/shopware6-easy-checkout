<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result;

use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Charge;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Checkout;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Company\ContactDetails;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Consumer;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Consumer\Address;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Consumer\Company;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Consumer\PrivatePerson;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Item;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\OrderDetails;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentDetails;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentDetails\CardDetails;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentDetails\InvoiceDetails;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\PaymentDetails\PaymentTypeEnum;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Refund;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\RefundStateEnum;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Summary;

final class RetrievePaymentResult extends AbstractResult
{
    public function __construct(private readonly Payment $payment)
    {
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    /**
     * @throws \Exception
     */
    public static function fromJson(string $string): RetrievePaymentResult
    {
        $payment = parent::jsonDeserialize($string)['payment'];

        return new self(
            self::createPayment($payment)
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \Exception
     */
    private static function createPayment(array $data): Payment
    {
        return new Payment(
            $data['paymentId'],
            new OrderDetails(...$data['orderDetails']),
            new Checkout(...$data['checkout']),
            new \DateTime($data['created']),
            self::createConsumer($data['consumer']),
            isset($data['terminated']) ? new \DateTime($data['terminated']) : null,
            self::createSummary($data['summary']),
            self::createPaymentDetails($data['paymentDetails']),
            isset($data['refunds']) ? self::createRefunds($data['refunds']) : null,
            isset($data['charges']) ? self::createCharges($data['charges']) : null,
            $data['myReference'] ?? null
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createConsumer(array $data): Consumer
    {
        return new Consumer(
            self::createAddress($data['shippingAddress']),
            self::createAddress($data['billingAddress']),
            self::createPrivatePerson($data['privatePerson']),
            self::createCompany($data['company'])
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createAddress(array $data): Address
    {
        return new Address(
            $data['addressLine1'] ?? null,
            $data['addressLine2'] ?? null,
            $data['receiverLine'] ?? null,
            $data['postalCode'] ?? null,
            $data['city'] ?? null,
            self::createPhoneNumber($data['phoneNumber'])
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createCompany(array $data): Company
    {
        return new Company(
            $data['merchantReference'] ?? null,
            $data['name'] ?? null,
            $data['registrationNumber'] ?? null,
            self::createContactDetails($data['contactDetails'])
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \Exception
     */
    private static function createPrivatePerson(array $data): PrivatePerson
    {
        return new PrivatePerson(
            $data['merchantReference'] ?? null,
            isset($data['dateOfBirth']) ? new \DateTime($data['dateOfBirth']) : null,
            $data['firstName'] ?? null,
            $data['lastName'] ?? null,
            $data['email'] ?? null,
            self::createPhoneNumber($data['phoneNumber'])
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createContactDetails(array $data): ContactDetails
    {
        return new ContactDetails(
            $data['firstName'] ?? null,
            $data['lastName'] ?? null,
            $data['email'] ?? null,
            self::createPhoneNumber($data['phoneNumber'])
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createSummary(array $data): Summary
    {
        return new Summary(
            $data['reservedAmount'] ?? 0,
            $data['chargedAmount'] ?? 0,
            $data['refundedAmount'] ?? 0,
            $data['cancelledAmount'] ?? 0
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createPaymentDetails(array $data): PaymentDetails
    {
        return new PaymentDetails(
            isset($data['paymentType']) ? PaymentTypeEnum::tryFrom($data['paymentType']) : null,
            $data['paymentMethod'] ?? null,
            self::createInvoiceDetails($data['invoiceDetails']),
            self::createCardDetails($data['cardDetails'])
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<Refund>
     */
    private static function createRefunds(array $data): array
    {
        return array_map([self::class, 'createRefund'], $data);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \Exception
     */
    private function createRefund(array $data): Refund
    {
        return new Refund(
            $data['refundId'],
            $data['amount'],
            RefundStateEnum::tryFrom($data['state']),
            new \DateTime($data['lastUpdated']),
            $this->createOrderItems($data['orderItems'])
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<Charge>
     */
    private static function createCharges(array $data): array
    {
        return array_map([self::class, 'createCharge'], $data);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \Exception
     */
    private function createCharge(array $data): Charge
    {
        return new Charge(
            $data['chargeId'],
            $data['amount'],
            new \DateTime($data['created']),
            $this->createOrderItems($data['orderItems'])
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<Item>
     */
    private function createOrderItems(array $data): array
    {
        return array_map([self::class, 'createItem'], $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createItem(array $data): Item
    {
        return new Item(
            $data['name'],
            $data['quantity'],
            $data['unit'],
            $data['unitPrice'],
            $data['grossTotalAmount'],
            $data['netTotalAmount'],
            $data['reference'],
            $data['taxRate'] ?? null,
            $data['taxAmount'] ?? null
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createInvoiceDetails(array $data): ?InvoiceDetails
    {
        if ($data === []) {
            return null;
        }

        return new InvoiceDetails(
            $data['invoiceNumber'] ?? null
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createCardDetails(array $data): ?CardDetails
    {
        if ($data === []) {
            return null;
        }

        return new CardDetails(
            $data['maskedPan'] ?? null,
            $data['expirationDate'] ?? null
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createPhoneNumber(array $data): ?PhoneNumber
    {
        if ($data === []) {
            return null;
        }

        return new PhoneNumber(
            $data['prefix'],
            $data['number']
        );
    }
}
