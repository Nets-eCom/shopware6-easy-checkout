<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder\PaymentRequest;

use NexiNets\CheckoutApi\Model\Request\Payment\Notification;
use NexiNets\CheckoutApi\Model\Request\Payment\Webhook;
use NexiNets\CheckoutApi\Model\Result\Webhook\EventNameEnum;
use NexiNets\Configuration\ConfigurationProvider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class NotificationBuilder
{
    private const WEBHOOK_NAMES = [
        EventNameEnum::PAYMENT_CHARGE_CREATED,
    ];

    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly RouterInterface $router
    ) {
    }

    public function create(SalesChannelContext $salesChannelContext): Notification
    {
        return new Notification(
            $this->createWebhooks($salesChannelContext)
        );
    }

    private function createWebhooks(SalesChannelContext $salesChannelContext): array
    {
        $webhooks = [];
        $authorizationString = $this->configurationProvider->getWebhookAuthorizationHeader($salesChannelContext->getSalesChannelId());
        foreach (self::WEBHOOK_NAMES as $eventName) {
            $webhooks[] = new Webhook(
                $eventName->value,
                $this->createWebhookUrl(),
                $authorizationString
            );
        }

        return $webhooks;
    }

    private function createWebhookUrl(): string
    {
        return $this->router->generate('nexinets_payment.nexinets.webhook', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
