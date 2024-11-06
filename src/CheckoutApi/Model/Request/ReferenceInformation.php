<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request;

final readonly class ReferenceInformation implements \JsonSerializable
{
    public function __construct(
        private string $checkoutUrl,
        private string $reference
    ) {
    }

    /**
     * @return array{
     *     checkoutUrl: string,
     *     reference: string
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'checkoutUrl' => $this->checkoutUrl,
            'reference' => $this->reference,
        ];
    }
}
