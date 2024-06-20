<?php

declare(strict_types=1);

namespace NexiNets\Lifecycle;

use Shopware\Core\Framework\Context;

interface PaymentMethodInstallerInterface
{
    public function install(Context $context): void;
}
