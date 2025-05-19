<?php declare(strict_types=1);

namespace Nexi\Checkout\Struct;

use Shopware\Core\Framework\Struct\Struct;

class TransactionDetailsStruct extends Struct
{
    public function __construct(
        private readonly ?string $paymentId,
        private readonly string $checkoutKey,
        private readonly string $checkoutJsUrl,
        private readonly string $language,
        private readonly string $handlePaymentUrl,
        private readonly ?string $targetPath = null,
    ) {
    }

    public function getPaymentId(): ?string
    {
        return $this->paymentId;
    }

    public function getCheckoutKey(): string
    {
        return $this->checkoutKey;
    }

    public function getCheckoutJsUrl(): string
    {
        return $this->checkoutJsUrl;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getHandlePaymentUrl(): string
    {
        return $this->handlePaymentUrl;
    }

    public function getTargetPath(): ?string
    {
        return $this->targetPath;
    }
}
