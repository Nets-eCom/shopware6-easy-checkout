<?php declare(strict_types=1);

namespace NexiNets\WebhookProcessor;

use NexiNets\CheckoutApi\Model\Webhook\EventNameEnum;
use NexiNets\CheckoutApi\Model\Webhook\Webhook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface WebhookInterface
{
    /**
     * @throws WebhookProcessorException
     */
    public function process(Webhook $webhook, SalesChannelContext $salesChannelContext): void;

    public function getEvent(): EventNameEnum;
}
