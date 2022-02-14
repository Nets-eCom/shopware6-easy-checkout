<?php

namespace Nets\Checkout\Subscriber;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use  Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class OrderPlacedEventSubscriber implements EventSubscriberInterface
{

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var RequestStack
     */
    private $request;

    public function __construct(SessionInterface $session, RequestStack $request)
    {
        $this->session = $session;
        $this->request = $request;
    }

    public static function getSubscribedEvents()
    {
        return [
            CheckoutOrderPlacedEvent::class => 'orderPlaced'
        ];
    }

    public function orderPlaced(CheckoutOrderPlacedEvent $event) {
        $paymentId = null;
		$orderId = $event->getOrderId();
		$_SESSION['orderId'] = $orderId;
        $currentRequest = $this->request->getCurrentRequest();
        if(!empty($currentRequest->get('paymentId'))) {
            $paymentId = $currentRequest->get('paymentId');
        }elseif(!empty($currentRequest->get('paymentid'))) {
            $paymentId = $currentRequest->get('paymentid');
        }elseif(!empty($this->session->get('nets_paymentId'))) {
            $paymentId = $this->session->get('nets_paymentId');
        }

        if($paymentId) {
            $event->getOrder()->setCustomFields(['nets_payment_id' => $paymentId]);
        }

    }
}
