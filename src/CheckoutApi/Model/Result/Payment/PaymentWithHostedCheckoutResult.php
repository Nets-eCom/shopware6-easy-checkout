<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\Payment;

use NexiNets\CheckoutApi\Model\Result\PaymentResult;
use NexiNets\CheckoutApi\Model\Shared\JsonDeserializeInterface;
use NexiNets\CheckoutApi\Model\Shared\JsonDeserializeTrait;

class PaymentWithHostedCheckoutResult extends PaymentResult implements JsonDeserializeInterface
{
    use JsonDeserializeTrait;

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
