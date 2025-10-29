<?php

declare(strict_types=1);

namespace Nexi\Checkout\Subscriber;

use Nexi\Checkout\Core\Content\NexiCheckout\Event\UpdateReferenceSend;

final class CacheCleanupOnUpdatedReferenceSubscriber extends BaseCacheCleanupSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            UpdateReferenceSend::class => 'onUpdateReferenceSend',
        ];
    }

    public function onUpdateReferenceSend(UpdateReferenceSend $event): void
    {
        $this->cleanCache($event->getTransaction());
    }
}
