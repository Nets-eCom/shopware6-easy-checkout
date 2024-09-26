<?php declare(strict_types=1);

namespace NexiNets\WebhookProcessor;

use NexiNets\CheckoutApi\Model\Webhook\WebhookInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface WebhookProcessorInterface
{
    /**
     * @throws WebhookProcessorException
     */
    public function process(WebhookInterface $webhook, SalesChannelContext $salesChannelContext): void;

    public function supports(WebhookInterface $webhook): bool;
}
