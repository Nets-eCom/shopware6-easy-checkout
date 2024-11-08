<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result;

use NexiNets\CheckoutApi\Model\Shared\JsonDeserializeInterface;
use NexiNets\CheckoutApi\Model\Shared\JsonDeserializeTrait;

final readonly class RefundChargeResult implements JsonDeserializeInterface
{
    use JsonDeserializeTrait;

    public function __construct(private string $refundId)
    {
    }

    public function getRefundId(): string
    {
        return $this->refundId;
    }

    public static function fromJson(string $string): self
    {
        return new self(...self::jsonDeserialize($string));
    }
}
