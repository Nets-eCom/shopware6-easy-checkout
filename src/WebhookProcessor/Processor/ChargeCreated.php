<?php declare(strict_types=1);

namespace NexiNets\WebhookProcessor\Processor;

use NexiNets\CheckoutApi\Model\Webhook\EventNameEnum;
use NexiNets\CheckoutApi\Model\Webhook\Webhook;
use NexiNets\WebhookProcessor\WebhookInterface;
use NexiNets\WebhookProcessor\WebhookProcessorException;
use Psr\Log\LoggerInterface;

final readonly class ChargeCreated implements WebhookInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws WebhookProcessorException
     */
    public function process(Webhook $webhook): void
    {
        $this->logger->info('payment.charge.created.v2 started', [
            'paymentId' => $webhook->getData()->getPaymentId(),
        ]);
        // @TODO implement
        $this->logger->info('payment.charge.created.v2 finished', [
            'paymentId' => $webhook->getData()->getPaymentId(),
        ]);
    }

    public function getEvent(): EventNameEnum
    {
        return EventNameEnum::PAYMENT_CHARGE_CREATED;
    }
}
