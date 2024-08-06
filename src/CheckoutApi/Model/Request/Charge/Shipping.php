<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\Charge;

class Shipping implements \JsonSerializable
{
    public function __construct(private readonly string $trackingNumber, private readonly string $provider)
    {
    }

    public function getTrackingNumber(): string
    {
        return $this->trackingNumber;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * @return array{
     *     trackingNumber: string,
     *     provider: string
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'trackingNumber' => $this->trackingNumber,
            'provider' => $this->provider,
        ];
    }
}
