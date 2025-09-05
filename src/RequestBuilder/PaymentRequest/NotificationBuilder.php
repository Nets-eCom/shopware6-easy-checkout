<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use NexiCheckout\Model\Request\Shared\Notification;
use NexiCheckout\Model\Request\Shared\Notification\Webhook;
use NexiCheckout\Model\Webhook\EventNameEnum;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class NotificationBuilder
{
    private const WEBHOOK_NAMES = [
        EventNameEnum::PAYMENT_CHECKOUT_COMPLETED,
        EventNameEnum::PAYMENT_RESERVATION_CREATED_V2,
        EventNameEnum::PAYMENT_CHARGE_CREATED,
        EventNameEnum::PAYMENT_REFUND_COMPLETED,
        EventNameEnum::PAYMENT_CANCEL_CREATED,
    ];

    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly RouterInterface $router
    ) {
    }

    public function create(string $salesChannelId): Notification
    {
        return new Notification(
            $this->createWebhooks($salesChannelId)
        );
    }

    /**
     * @return Webhook[]
     */
    private function createWebhooks(string $salesChannelId): array
    {
        $webhooks = [];
        $authorizationString = $this->configurationProvider->getWebhookAuthorizationHeader($salesChannelId);
        $webhookUrl = $this->createWebhookUrl();
        foreach (self::WEBHOOK_NAMES as $eventName) {
            $webhooks[] = new Webhook(
                $eventName->value,
                $webhookUrl,
                $authorizationString
            );
        }

        return $webhooks;
    }

    private function createWebhookUrl(): string
    {
        return $this->router->generate('payment.nexicheckout.webhook', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
