<?php


namespace Nets\Checkout\Subscriber;


use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderPlacedEventSubscriber implements EventSubscriberInterface
{

    public static function getSubscribedEvents()
    {
        return [
            CheckoutOrderPlacedEvent::class => 'orderPlaced'
        ];
    }

    public function orderPlaced(CheckoutOrderPlacedEvent $event) {
        $paymentId = null;
        if (isset($_GET['paymentId'])) $paymentId = $_GET['paymentId'];
        if (isset($_GET['paymentid'])) $paymentId = $_GET['paymentid'];
        if($paymentId) {
            $event->getOrder()->setCustomFields(['nets_payment_id' => $paymentId]);
        }
    }
}
