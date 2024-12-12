<?php

declare(strict_types=1);

namespace NexiNets\Core\Content\NetsCheckout\Event;

use NexiNets\CheckoutApi\Model\Webhook\WebhookInterface;
use Symfony\Contracts\EventDispatcher\Event;

final class WebhookProcessed extends Event
{
    public function __construct(private readonly WebhookInterface $webhook, private readonly string $paymentId)
    {
    }

    public function getWebhook(): WebhookInterface
    {
        return $this->webhook;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }
}
