<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Api\Exception;

class ClientErrorPaymentApiException extends PaymentApiException
{
    /**
     * @var array<string, string[]>
     */
    private readonly array $errors;

    public function __construct(
        string $message,
        string $errors,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->errors = json_decode($errors, true, 512, \JSON_THROW_ON_ERROR)['errors'];
    }

    /**
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
