<?php

declare(strict_types=1);

namespace NexiNets\Order\Exception;

class OrderCancelException extends OrderException
{
    public function __construct(private readonly string $paymentId, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Couldn\'t charge payment with id: %s', $paymentId), $code, $previous);
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }
}
