<?php

declare(strict_types=1);

namespace NexiNets\Tests\Order\Mother;

use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Charge;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Checkout;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Consumer;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Consumer\Address;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Consumer\Company;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Consumer\PrivatePerson;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\OrderDetails;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Payment;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Refund;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\RefundStateEnum;
use NexiNets\CheckoutApi\Model\Result\RetrievePayment\Summary;
use NexiNets\CheckoutApi\Model\Result\RetrievePaymentResult;

class RetrievePaymentResultMother
{
    public static function reserved(): RetrievePaymentResult
    {
        return new RetrievePaymentResult(
            new Payment(
                '025400006091b1ef6937598058c4e487',
                new OrderDetails(100, 'EUR'),
                new Checkout('https://example.com/checkout', null),
                new \DateTimeImmutable(),
                new Consumer(
                    new Address(null, null, null, null, null),
                    new Address(null, null, null, null, null),
                    new PrivatePerson(null, null, null, null, null, null),
                    new Company(null, null, null, null),
                ),
                null,
                self::createSummary(
                    100,
                    0,
                    0,
                    0
                ),
                null,
                null
            )
        );
    }

    public static function fullyCharged(): RetrievePaymentResult
    {
        return new RetrievePaymentResult(
            new Payment(
                '025400006091b1ef6937598058c4e487',
                new OrderDetails(100, 'EUR'),
                new Checkout('https://example.com/checkout', null),
                new \DateTimeImmutable(),
                new Consumer(
                    new Address(null, null, null, null, null),
                    new Address(null, null, null, null, null),
                    new PrivatePerson(null, null, null, null, null, null),
                    new Company(null, null, null, null),
                ),
                null,
                self::createSummary(
                    100,
                    100,
                    0,
                    0
                ),
                null,
                null,
                [new Charge('test_charge_id', 100, new \DateTimeImmutable(), [])]
            )
        );
    }

    public static function fullyRefunded(): RetrievePaymentResult
    {
        return new RetrievePaymentResult(
            new Payment(
                '025400006091b1ef6937598058c4e487',
                new OrderDetails(100, 'EUR'),
                new Checkout('https://example.com/checkout', null),
                new \DateTimeImmutable(),
                new Consumer(
                    new Address(null, null, null, null, null),
                    new Address(null, null, null, null, null),
                    new PrivatePerson(null, null, null, null, null, null),
                    new Company(null, null, null, null),
                ),
                null,
                self::createSummary(
                    100,
                    100,
                    100,
                    0
                ),
                null,
                [new Refund('test_refund_id', 100, RefundStateEnum::COMPLETED, new \DateTimeImmutable(), [])],
                [new Charge('test_charge_id', 100, new \DateTimeImmutable(), [])]
            )
        );
    }

    public static function partiallyRefunded(): RetrievePaymentResult
    {
        return new RetrievePaymentResult(
            new Payment(
                '025400006091b1ef6937598058c4e487',
                new OrderDetails(100, 'EUR'),
                new Checkout('https://example.com/checkout', null),
                new \DateTimeImmutable(),
                new Consumer(
                    new Address(null, null, null, null, null),
                    new Address(null, null, null, null, null),
                    new PrivatePerson(null, null, null, null, null, null),
                    new Company(null, null, null, null),
                ),
                null,
                self::createSummary(
                    100,
                    100,
                    50,
                    0
                ),
                null,
                [new Refund('test_refund_id', 50, RefundStateEnum::COMPLETED, new \DateTimeImmutable(), [])],
                [new Charge('test_charge_id', 50, new \DateTimeImmutable(), [])]
            )
        );
    }

    private static function createSummary(
        int $reservedAmount,
        int $chargedAmount,
        int $refundedAmount,
        int $cancelledAmount
    ): Summary {
        return new Summary(
            $reservedAmount,
            $chargedAmount,
            $refundedAmount,
            $cancelledAmount
        );
    }
}
