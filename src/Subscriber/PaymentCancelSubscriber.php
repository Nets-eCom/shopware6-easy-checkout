<?php

declare(strict_types=1);

namespace NexiNets\Subscriber;

use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\Order\OrderCancel;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class PaymentCancelSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private OrderCancel $orderCancel,
        private EntityRepository $orderRepository,
        private OrderTransactionStateHandler $orderTransactionStateHandler,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'onOrderCancel',
        ];
    }

    public function onOrderCancel(StateMachineTransitionEvent $event): void
    {
        if (!$this->isOrderCancellationEvent($event)) {
            return;
        }

        $context = $event->getContext();

        /** @var OrderEntity $order */
        $order = $this
            ->orderRepository
            ->search(
                (new Criteria([$event->getEntityId()]))
                    ->addAssociations(
                        [
                            'transactions',
                            'transactions.stateMachineState',
                        ]
                    ),
                $context
            )
            ->first();

        $transaction = $order->getTransactions()->first();

        if (!$transaction instanceof OrderTransactionEntity) {
            return;
        }

        if ($transaction->getStateMachineState()->getTechnicalName() !== OrderTransactionStates::STATE_AUTHORIZED) {
            return;
        }

        $paymentId = $transaction->getCustomFieldsValue(
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID
        );

        if ($paymentId === null) {
            return;
        }

        $this->orderCancel->cancel($order);
        $this->orderTransactionStateHandler->cancel($transaction->getId(), $context);
    }

    private function isOrderCancellationEvent(StateMachineTransitionEvent $event): bool
    {
        return $event->getEntityName() === OrderDefinition::ENTITY_NAME
            && $event->getToPlace()->getTechnicalName() === OrderStates::STATE_CANCELLED;
    }
}
