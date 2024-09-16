<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook;

use NexiNets\CheckoutApi\Model\Webhook\Shared\Data;

interface WebhookInterface
{
    public function getEvent(): EventNameEnum;

    public function getData(): Data;
}
