<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Factory\Provider;

use NexiNets\CheckoutApi\Http\Configuration;

interface HttpClientConfigurationProviderInterface
{
    public function provide(string $secretKey): Configuration;
}
