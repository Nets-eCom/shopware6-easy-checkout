<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\Payment;

use NexiNets\CheckoutApi\Model\Result\PaymentResult;

class PaymentWithEmbeddedCheckoutResult extends PaymentResult
{
    public function __construct(protected string $paymentId)
    {
        parent::__construct($paymentId);
    }

    public static function fromJson(string $string): PaymentWithEmbeddedCheckoutResult
    {
        return new self(...self::jsonDeserialize($string));
    }
}
