<?php declare(strict_types=1);

namespace Nexi\Checkout\Struct;

use Shopware\Core\Framework\Struct\Struct;

class TransactionDetailsStruct extends Struct
{
    public function __construct(
        private ?string $paymentId,
    ) {
    }

    public function getPaymentId(): ?string
    {
        return $this->paymentId;
    }
}
