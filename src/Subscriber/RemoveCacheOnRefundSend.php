<?php declare(strict_types=1);

namespace NexiNets\Subscriber;

use NexiNets\Core\Content\NetsCheckout\Event\RefundChargeSend;
use NexiNets\Fetcher\PaymentFetcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RemoveCacheOnRefundSend implements EventSubscriberInterface
{
    public function __construct(private readonly PaymentFetcher $paymentFetcher)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RefundChargeSend::class => 'onRefundChargeSend',
        ];
    }

    public function onRefundChargeSend(RefundChargeSend $event): void
    {
        $paymentId = $event->getTransaction()->getPaymentMethodId();

        $this->paymentFetcher->removeCache($paymentId);
    }
}
