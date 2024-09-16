<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\Payment;

use NexiNets\CheckoutApi\Model\Result\PaymentResult;
use NexiNets\CheckoutApi\Model\Shared\JsonDeserializeInterface;
use NexiNets\CheckoutApi\Model\Shared\JsonDeserializeTrait;

class PaymentWithEmbeddedCheckoutResult extends PaymentResult implements JsonDeserializeInterface
{
    use JsonDeserializeTrait;

    public function __construct(protected string $paymentId)
    {
        parent::__construct($paymentId);
    }

    public static function fromJson(string $string): PaymentWithEmbeddedCheckoutResult
    {
        return new self(...self::jsonDeserialize($string));
    }
}
