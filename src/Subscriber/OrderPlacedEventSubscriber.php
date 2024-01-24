<?php

namespace Nets\Checkout\Subscriber;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class OrderPlacedEventSubscriber implements EventSubscriberInterface
{
    private RequestStack $request;

    public function __construct(RequestStack $request)
    {
        $this->request = $request;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'orderPlaced',
        ];
    }

    public function orderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $paymentId      = null;
        $orderId        = $event->getOrder()->getId();
        $currentRequest = $this->request->getCurrentRequest();
        $session        = $currentRequest->getSession();
        $session->set('orderId', $orderId);

        if (!empty($currentRequest->query->get('paymentId'))) {
            $paymentId = $currentRequest->query->get('paymentId');
        } elseif (!empty($currentRequest->query->get('paymentid'))) {
            $paymentId = $currentRequest->query->get('paymentid');
        } elseif (!empty($session->get('nets_paymentId'))) {
            $paymentId = $session->get('nets_paymentId');
        }

        if ($paymentId) {
            $event->getOrder()->setCustomFields(['nets_payment_id' => $paymentId]);
        }
    }
}
