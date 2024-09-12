<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook;

interface WebhookInterface
{
    public function getEvent(): EventNameEnum;

    public function getData(): Data;
}
