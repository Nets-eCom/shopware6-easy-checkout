<?php

declare(strict_types=1);

namespace Nexi\Checkout\Order\Exception;

class OrderReferenceUpdateException extends OrderException
{
    public function __construct(private readonly string $paymentId, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Couldn\'t update reference information for payment: %s', $paymentId), $code, $previous);
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }
}
