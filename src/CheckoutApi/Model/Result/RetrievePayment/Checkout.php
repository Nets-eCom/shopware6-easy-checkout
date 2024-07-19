<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment;

readonly class Checkout
{
    public function __construct(private string $url, private ?string $cancelUrl)
    {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getCancelUrl(): ?string
    {
        return $this->cancelUrl;
    }
}
