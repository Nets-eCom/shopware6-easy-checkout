<?php

declare(strict_types=1);

namespace NexiNets\Subscriber;

use NexiNets\Core\Content\NetsCheckout\Event\WebhookProcessed;
use NexiNets\Fetcher\CachablePaymentFetcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class CacheCleanupOnWebhookProcessedSubscriber implements EventSubscriberInterface
{
    public function __construct(private CachablePaymentFetcherInterface $fetcher)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            WebhookProcessed::class => 'onWebhookProcessed',
        ];
    }

    public function onWebhookProcessed(WebhookProcessed $event): void
    {
        $this->fetcher->removeCache($event->getPaymentId());
    }
}
