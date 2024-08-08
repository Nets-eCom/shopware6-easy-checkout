<?php declare(strict_types=1);

namespace NexiNets\WebhookProcessor\Processor;

use NexiNets\CheckoutApi\Model\Result\Webhook\EventNameEnum;
use NexiNets\CheckoutApi\Model\Result\Webhook\Webhook;
use NexiNets\WebhookProcessor\WebhookInterface;

final class ChargeCreated implements WebhookInterface
{
    /**
     * @throws WebhookProcessorException
     */
    public function process(Webhook $webhook): void
    {
        // @TODO implement
    }

    public function getEvent(): EventNameEnum
    {
        return EventNameEnum::PAYMENT_CHARGE_CREATED;
    }
}
