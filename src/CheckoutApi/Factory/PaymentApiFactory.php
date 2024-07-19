<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Factory;

use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Http\Configuration;

class PaymentApiFactory
{
    public function __construct(private readonly HttpClientFactory $clientFactory)
    {
    }

    public function create(
        string $secretKey,
        bool $isLiveMode,
        ?string $commerceTag
    ): PaymentApi {
        return new PaymentApi(
            $this->clientFactory->createWithConfiguration(new Configuration($secretKey, $commerceTag)),
            $isLiveMode,
        );
    }
}
