<?php

declare(strict_types=1);

namespace Nexi\Checkout\Subscriber;

use Nexi\Checkout\Core\Content\NexiCheckout\Event\CancelSend;

final class CacheCleanupOnCancelSendSubscriber extends BaseCacheCleanupSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            CancelSend::class => 'onCancelSend',
        ];
    }

    public function onCancelSend(CancelSend $event): void
    {
        $this->cleanCache($event->getTransaction());
    }
}
