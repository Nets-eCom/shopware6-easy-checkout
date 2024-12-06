<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Api\Exception;

use NexiNets\CheckoutApi\Api\ErrorCodeEnum;

class InternalErrorPaymentApiException extends PaymentApiException
{
    private readonly ErrorCodeEnum $internalCode;

    private readonly string $internalMessage;

    private readonly string $source;

    /**
     * @throws \JsonException
     */
    public function __construct(
        string $contents,
        ?\Throwable $previous = null
    ) {
        parent::__construct(\sprintf('Internal API error: %s', $contents), previous: $previous);

        $error = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);

        $this->internalCode = ErrorCodeEnum::from((int) $error['code']);
        $this->internalMessage = $error['message'];
        $this->source = $error['source'];
    }

    public function getInternalMessage(): string
    {
        return $this->internalMessage;
    }

    public function getInternalCode(): ErrorCodeEnum
    {
        return $this->internalCode;
    }

    public function getSource(): string
    {
        return $this->source;
    }
}
