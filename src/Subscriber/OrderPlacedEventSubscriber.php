<?php

namespace Nets\Checkout\Subscriber;

use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class OrderPlacedEventSubscriber implements EventSubscriberInterface
{
    private RequestStack $request;
    private EasyApiService $api;


    public function __construct(RequestStack $request, EasyApiService $api)
    {
        $this->request = $request;
        $this->api = $api;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'orderPlaced',
        ];
    }

    public function orderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $paymentId = null;
        $order = $event->getOrder();
        $orderId = $order->getId();
        $salesChannelId = $event->getSalesChannelId();
        $currentRequest = $this->request->getCurrentRequest();
        $session = $currentRequest->getSession();
        $session->set('orderId', $orderId);

        if ($currentRequest->request->get('paymentId') !== null) {
            $paymentId = $currentRequest->request->get('paymentId');
        } elseif ($currentRequest->request->get('paymentid') !== null) {
            $paymentId = $currentRequest->request->get('paymentid');
        } elseif ($currentRequest->query->get('paymentId') !== null) {
            $paymentId = $currentRequest->query->get('paymentId');
        } elseif ($currentRequest->query->get('paymentid') !== null) {
            $paymentId = $currentRequest->query->get('paymentid');
        } elseif ($session->get('nets_paymentId') !== null) {
            $paymentId = $session->get('nets_paymentId');
        }

        if ($paymentId !== null) {
            $payment = $this->api->getPayment($paymentId, $salesChannelId);

            $this->api->updateReference(
                $paymentId,
                json_encode([
                    'reference' => $order->getOrderNumber(),
                    'checkoutUrl' => $payment->getCheckoutUrl(),
                ])
            );
        }
    }
}
