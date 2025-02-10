<?php

declare(strict_types=1);

namespace Nexi\Checkout\Lifecycle;

use Doctrine\DBAL\Connection;

interface UserDataRemoverInterface
{
    public function removeUserData(Connection $connection): void;
}
