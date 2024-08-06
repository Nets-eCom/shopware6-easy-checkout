<?php declare(strict_types=1);

namespace NexiNets\WebhookProcessor;

use NexiNets\CheckoutApi\Model\Result\Webhook\Webhook;

interface WebhookInterface
{
    /**
     * @throws WebhookProcessorException
     */
    public function process(Webhook $webhook): void;

    public function getEvent(): string;
}
