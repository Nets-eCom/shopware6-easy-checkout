<?php

declare(strict_types=1);

namespace Nexi\Checkout\Struct;

use Shopware\Core\Framework\Struct\Struct;

class SplitPaymentDetailsStruct extends Struct
{
    /**
     * @param list<array{value: string, label: string}> $subselections
     */
    public function __construct(
        private readonly array $subselections = [],
        private readonly ?string $createEmbeddedPaymentUrl = null,
    ) {
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function getSubselections(): array
    {
        return $this->subselections;
    }

    public function getCreateEmbeddedPaymentUrl(): ?string
    {
        return $this->createEmbeddedPaymentUrl;
    }
}
