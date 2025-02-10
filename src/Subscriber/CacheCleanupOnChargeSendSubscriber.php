<?php

declare(strict_types=1);

namespace Nexi\Checkout\Subscriber;

use Nexi\Checkout\Core\Content\NexiCheckout\Event\ChargeSend;

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
