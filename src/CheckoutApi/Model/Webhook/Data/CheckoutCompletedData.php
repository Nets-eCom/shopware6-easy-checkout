<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Webhook\Data;

use NexiNets\CheckoutApi\Model\Webhook\Shared\Data;

class CheckoutCompletedData extends Data
{
    public function __construct(
        string $paymentId,
        private readonly Order $order,
        private readonly Consumer $consumer
    ) {
        parent::__construct($paymentId);
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getConsumer(): Consumer
    {
        return $this->consumer;
    }
}
