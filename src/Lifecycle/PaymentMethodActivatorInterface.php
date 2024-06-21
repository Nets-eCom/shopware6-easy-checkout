<?php

declare(strict_types=1);

namespace NexiNets\Lifecycle;

use Shopware\Core\Framework\Context;

interface PaymentMethodActivatorInterface
{
    public function activate(Context $context): void;
}
