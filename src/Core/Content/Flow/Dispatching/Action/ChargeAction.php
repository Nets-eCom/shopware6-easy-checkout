<?php

declare(strict_types=1);

namespace Nexi\Checkout\Core\Content\Flow\Dispatching\Action;

use Nexi\Checkout\Order\OrderCharge;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\OrderAware;

class ChargeAction extends FlowAction
{
    public function __construct(
        private readonly OrderCharge $orderCharge
    ) {
    }

    public static function getName(): string
    {
        return 'action.nexicheckout.charge';
    }

    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasData(OrderAware::ORDER)) {
            return;
        }

        $this->orderCharge->fullCharge($flow->getData(OrderAware::ORDER));
    }
}
