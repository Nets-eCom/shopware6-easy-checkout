<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Request\Payment;

final readonly class ConsumerType implements \JsonSerializable
{
    /**
     * @param list<string> $supportedTypes
     */
    public function __construct(
        private string $default,
        private array $supportedTypes
    ) {
    }

    /**
     * @return array{
     *     default: string,
     *     supportedTypes: list<string>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'default' => $this->default,
            'supportedTypes' => $this->supportedTypes,
        ];
    }
}
