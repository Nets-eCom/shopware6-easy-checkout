<?php declare(strict_types=1);

namespace NexiNets\WebhookProcessor;

use NexiNets\CheckoutApi\Model\Webhook\EventNameEnum;
use NexiNets\CheckoutApi\Model\Webhook\WebhookInterface as WebhookModelInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface WebhookProcessorInterface
{
    /**
     * @throws WebhookProcessorException
     */
    public function process(WebhookModelInterface $webhook, SalesChannelContext $salesChannelContext): void;

    public function getEvent(): EventNameEnum;
}
