<?php

declare(strict_types=1);

namespace NexiNets\Subscriber;

use NexiNets\Core\Content\NetsCheckout\Event\ChargeSend;

final class CacheCleanupOnChargeSendSubscriber extends BaseCacheCleanupSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            ChargeSend::class => 'onChargeSend',
        ];
    }

    public function onChargeSend(ChargeSend $event): void
    {
        $this->cleanCache($event->getTransaction());
    }
}
