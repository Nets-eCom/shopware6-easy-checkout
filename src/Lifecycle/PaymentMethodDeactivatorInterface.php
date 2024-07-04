<?php

declare(strict_types=1);

namespace NexiNets\Lifecycle;

use Shopware\Core\Framework\Context;

interface PaymentMethodDeactivatorInterface
{
    public function deactivate(Context $context): void;
}
