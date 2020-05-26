<?php

namespace Nets\Checkout\Subscriber;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Nets\Checkout\Service\Easy\Api\TransactionDetailsStruct;
use Shopware\Core\Framework\Context;

class CheckioutFinishPageSubscriber implements EventSubscriberInterface
{
    private $orderRepository;

    public function __construct(EntityRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutFinishPageLoadedEvent::class => 'onCheckoutFinishLoaded',
        ];
    }

    public function onCheckoutFinishLoaded(CheckoutFinishPageLoadedEvent $event)
    {
        $paymentStruct = new TransactionDetailsStruct();
        $page = $event->getPage();
        $paymentStruct->assign(['test' => $page->getOrder()->getId()]);
        $context = Context::createDefaultContext();
        $criteria = new Criteria([$_GET['orderId']]);
        $criteria->addAssociation('transactions');
        $order = $this->orderRepository->search($criteria, $context)->first();
        $transactionCollection = $order->getTransactions();
        $transaction = $transactionCollection->first();
        $customFields = $transaction->getCustomFields();
        if (isset($customFields['nets_easy_payment_details']['transaction_id'])) {
            $paymentStruct->assign(['transaction_id' => $customFields['nets_easy_payment_details']['transaction_id'] ]);
            $page->addExtension('nets_transaction_id', $paymentStruct);
        }
    }
}
