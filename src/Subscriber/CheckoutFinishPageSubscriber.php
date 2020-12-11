<?php

namespace Nets\Checkout\Subscriber;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Nets\Checkout\Service\Easy\Api\TransactionDetailsStruct;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\RequestStack;

class CheckoutFinishPageSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * CheckoutFinishPageSubscriber constructor.
     * @param EntityRepositoryInterface $orderRepository
     * @param RequestStack $requestStack
     */
    public function __construct(EntityRepositoryInterface $orderRepository, RequestStack $requestStack)
    {
        $this->orderRepository = $orderRepository;
        $this->requestStack = $requestStack;
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

    /**
     * @param CheckoutFinishPageLoadedEvent $event
     */
    public function onCheckoutFinishLoaded(CheckoutFinishPageLoadedEvent $event)
    {
        $paymentStruct = new TransactionDetailsStruct();
        $page = $event->getPage();
        $context = Context::createDefaultContext();
        $criteria = new Criteria([$this->requestStack->getCurrentRequest()->get('orderId')]);
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
