<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result;

use NexiNets\CheckoutApi\Model\Result\ChargeResult\Invoice;
use NexiNets\CheckoutApi\Model\Shared\JsonDeserializeInterface;
use NexiNets\CheckoutApi\Model\Shared\JsonDeserializeTrait;

final readonly class ChargeResult implements JsonDeserializeInterface
{
    use JsonDeserializeTrait;

    public function __construct(
        private string $chargeId,
        private ?Invoice $invoice = null
    ) {
    }

    public function getChargeId(): string
    {
        return $this->chargeId;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public static function fromJson(string $string): ChargeResult
    {
        return new self(...self::jsonDeserialize($string));
    }
}
