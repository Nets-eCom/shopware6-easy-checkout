<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\Payment;

final readonly class Webhook implements \JsonSerializable
{
    public function __construct(
        private string $eventName,
        private string $url,
        private string $authorization,
    ) {
    }

    /**
     * @return array{
     *     eventName: string,
     *     url: string,
     *     authorization: string
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'eventName' => $this->eventName,
            'url' => $this->url,
            'authorization' => $this->authorization,
        ];
    }
}
