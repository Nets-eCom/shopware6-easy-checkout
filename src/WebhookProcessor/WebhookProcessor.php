<?php declare(strict_types=1);

namespace NexiNets\WebhookProcessor;

use NexiNets\CheckoutApi\Model\Webhook\WebhookInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class WebhookProcessor
{
    /**
     * @param WebhookProcessorInterface[] $webhookProcessors
     */
    public function __construct(
        private iterable $webhookProcessors
    ) {
    }

    /**
     * @throws WebhookProcessorException
     * @throws \RuntimeException
     */
    public function process(WebhookInterface $webhook, SalesChannelContext $salesChannelContext): void
    {
        foreach ($this->webhookProcessors as $webhookProcessor) {
            if ($webhook->getEvent() === $webhookProcessor->getEvent()) {
                $webhookProcessor->process($webhook, $salesChannelContext);

                return;
            }
        }

        throw new \RuntimeException('Webhook event processor missing');
    }
}
