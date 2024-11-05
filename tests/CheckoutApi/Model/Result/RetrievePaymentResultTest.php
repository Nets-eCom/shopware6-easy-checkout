<?php

declare(strict_types=1);

namespace CheckoutApi\Model\Result;

use NexiNets\CheckoutApi\Model\Result\RetrievePaymentResult;
use PHPUnit\Framework\TestCase;

class RetrievePaymentResultTest extends TestCase
{
    private const PAYLOAD = <<<JSON
    {
        "payment": {
            "paymentId": "025400006091b1ef6937598058c4e487",
            "summary": {
                "reservedAmount": 100
            },
            "consumer": {
                "shippingAddress": {},
                "billingAddress": {},
                "privatePerson": {},
                "company": {}
            },
            "paymentDetails": {},
            "orderDetails": {
                "amount": 100,
                "currency": "EUR"
            },
            "checkout": {
                "url": "https://example.com/checkout",
                "cancelUrl": null
            },
            "created": "2019-08-24T14:15:22Z",
            "refunds": [],
            "charges": []
        }
    }
JSON;

    public function testItCanInstantiateFromJsonString(): void
    {
        $this->assertInstanceOf(
            RetrievePaymentResult::class,
            RetrievePaymentResult::fromJson(self::PAYLOAD)
        );
    }
}
