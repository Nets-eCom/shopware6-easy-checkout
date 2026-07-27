<?php

declare(strict_types=1);

namespace Nexi\Checkout\PaymentMethods;

interface PaymentMethodsFetcherInterface
{
    /**
     * @return list<string>
     */
    public function fetchAvailableMethodNames(?string $salesChannelId, ?string $currencyIsoCode = null): array;
}
