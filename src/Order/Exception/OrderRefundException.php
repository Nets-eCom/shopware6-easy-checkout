<?php

declare(strict_types=1);

namespace NexiNets\Order\Exception;

class OrderRefundException extends OrderException
{
    public function __construct(private readonly string $chargeId, ?int $code = null, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Couldn\'t refund charge with id: %s', $this->chargeId), $code, $previous);
    }

    public function getChargeId(): string
    {
        return $this->chargeId;
    }
}
