<?php

declare(strict_types=1);

namespace NexiNets\Order\Exception;

class OrderChargeException extends OrderException
{
    public function __construct(private readonly string $paymentId, ?int $code = null, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Couldn\'t charge payment with id: %s', $paymentId), $code, $previous);
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }
}
