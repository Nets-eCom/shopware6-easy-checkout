<?php

declare(strict_types=1);

namespace Nexi\Checkout\Lifecycle;

use Shopware\Core\Framework\Context;

interface PaymentMethodInstallerInterface
{
    public function install(Context $context): void;
}
