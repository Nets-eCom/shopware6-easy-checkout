<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result;

use NexiNets\CheckoutApi\Model\Result\ChargeResult\Invoice;

final class ChargeResult extends AbstractResult
{
    public function __construct(
        private readonly string $chargeId,
        private readonly ?Invoice $invoice = null
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
