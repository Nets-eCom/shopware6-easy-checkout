<?php

namespace Nets\Checkout\Subscriber;

use Nets\Checkout\Service\Easy\Api\TransactionDetailsStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CheckoutFinishPageSubscriber implements EventSubscriberInterface
{
    private EntityRepository $orderRepository;

    /**
     * CheckoutFinishPageSubscriber constructor.
     */
    public function __construct(EntityRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutFinishPageLoadedEvent::class => 'onCheckoutFinishLoaded',
        ];
    }

    public function onCheckoutFinishLoaded(CheckoutFinishPageLoadedEvent $event): void
    {
        $paymentStruct = new TransactionDetailsStruct();
        $page          = $event->getPage();
        $context       = Context::createDefaultContext();
        $criteria      = new Criteria([$event->getRequest()->query->get('orderId')]);
        $criteria->addAssociation('transactions');
        $order                 = $this->orderRepository->search($criteria, $context)->first();
        $transactionCollection = $order->getTransactions();
        $transaction           = $transactionCollection->first();
        $customFields          = $transaction->getCustomFields();

        if (isset($customFields['nets_easy_payment_details']['transaction_id'])) {
            $paymentStruct->assign(['transaction_id' => $customFields['nets_easy_payment_details']['transaction_id']]);
            $page->addExtension('nets_transaction_id', $paymentStruct);
        }
    }
}
