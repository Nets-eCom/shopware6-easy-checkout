<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\Payment;

use NexiNets\CheckoutApi\Model\Result\PaymentResult;

class PaymentWithHostedCheckoutResult extends PaymentResult
{
    public function __construct(
        protected string $paymentId,
        private readonly string $hostedPaymentPageUrl
    ) {
        parent::__construct($paymentId);
    }

    public function getHostedPaymentPageUrl(): string
    {
        return $this->hostedPaymentPageUrl;
    }

    public static function fromJson(string $string): PaymentWithHostedCheckoutResult
    {
        return new self(...self::jsonDeserialize($string));
    }
}
