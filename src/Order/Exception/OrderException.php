<?php

declare(strict_types=1);

namespace Nexi\Checkout\Order\Exception;

abstract class OrderException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
