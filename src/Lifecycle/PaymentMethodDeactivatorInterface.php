<?php

declare(strict_types=1);

namespace Nexi\Checkout\Lifecycle;

use Shopware\Core\Framework\Context;

interface PaymentMethodDeactivatorInterface
{
    public function deactivate(Context $context): void;
}
