<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Http;

class Configuration
{
    public function __construct(
        protected string $secretKey,
        protected ?string $commercePlatformTag = null,
    ) {
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function getCommercePlatformTag(): ?string
    {
        return $this->commercePlatformTag;
    }
}
