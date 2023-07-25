<?php

namespace Nets\Checkout\Subscriber;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use  Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class OrderPlacedEventSubscriber implements EventSubscriberInterface
{
    private RequestStack $request;

    public function __construct(RequestStack $request)
    {
        $this->request = $request;
    }

    public static function getSubscribedEvents() : array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'orderPlaced'
        ];
    }

    public function orderPlaced(CheckoutOrderPlacedEvent $event) {
        $paymentId = null;
		$orderId = $event->getOrder()->getId();
        $session = $this->request->getSession();
		$session->set("orderId", $orderId);
        $currentRequest = $this->request->getCurrentRequest();
        if(!empty($currentRequest->get('paymentId'))) {
            $paymentId = $currentRequest->get('paymentId');
        }elseif(!empty($currentRequest->get('paymentid'))) {
            $paymentId = $currentRequest->get('paymentid');
        }elseif(!empty($session->get('nets_paymentId'))) {
            $paymentId = $session->get('nets_paymentId');
        }

        if($paymentId) {
            $event->getOrder()->setCustomFields(['nets_payment_id' => $paymentId]);
        }

    }
}
