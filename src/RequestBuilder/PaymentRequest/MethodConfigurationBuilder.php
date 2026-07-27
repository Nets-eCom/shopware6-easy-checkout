<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder\PaymentRequest;

use NexiCheckout\Model\Request\Payment\MethodConfiguration;

class MethodConfigurationBuilder
{
    private const COMPOUND_METHODS = [
        'GooglePay' => ['GooglePay', 'Card'],
        'ApplePay' => ['ApplePay', 'Card'],
    ];

    /**
     * @return list<MethodConfiguration>
     */
    public function build(?string $subselection): array
    {
        if ($subselection === null || $subselection === '') {
            return [];
        }

        $methods = self::COMPOUND_METHODS[$subselection] ?? [$subselection];

        return array_map(
            static fn (string $name) => new MethodConfiguration($name, true),
            $methods
        );
    }
}
