<?php declare(strict_types=1);

namespace NexiNets\WebhookProcessor;

use NexiNets\CheckoutApi\Model\Result\Webhook\Webhook;

final class WebhookProcessor
{
    /**
     * @var WebhookInterface[]
     */
    private iterable $webhookProcessors = [];

    public function __construct(
        WebhookInterface ...$webhookProcessors
    ) {
        $this->webhookProcessors = $webhookProcessors;
    }

    /**
     * @throws WebhookProcessorException
     * @throws \RuntimeException
     */
    public function process(Webhook $webhook): void
    {
        foreach ($this->webhookProcessors as $webhookProcessor) {
            if ($webhook->getEvent() === $webhookProcessor->getEvent()) {
                $webhookProcessor->process($webhook);

                return;
            }
        }

        throw new \RuntimeException('Webhook event processor missing');
    }
}
