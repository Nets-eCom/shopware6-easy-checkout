<?php
declare(strict_types=1);

namespace Nexi\Checkout\Subscriber;

use Nexi\Checkout\Core\Content\NexiCheckout\Event\RefundChargeSend;

final class CacheCleanupOnRefundSendSubscriber extends BaseCacheCleanupSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            RefundChargeSend::class => 'onRefundSend',
        ];
    }

    public function onRefundSend(RefundChargeSend $event): void
    {
        $this->cleanCache($event->getTransaction());
    }
}
