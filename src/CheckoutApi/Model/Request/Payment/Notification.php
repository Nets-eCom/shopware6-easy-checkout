<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\Payment;

final class Notification implements \JsonSerializable
{
    /**
     * @param list<Webhook> $webHooks
     */
    public function __construct(private array $webHooks)
    {
    }

    public function addWebhook(Webhook $webhook): void
    {
        $this->webHooks[] = $webhook;
    }

    /**
     * @return array{webHooks: list<Webhook>}
     */
    public function jsonSerialize(): array
    {
        return [
            'webHooks' => $this->webHooks,
        ];
    }
}
