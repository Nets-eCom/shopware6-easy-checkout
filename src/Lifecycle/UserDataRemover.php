<?php

declare(strict_types=1);

namespace Nexi\Checkout\Lifecycle;

use Doctrine\DBAL\Connection;

final readonly class UserDataRemover implements UserDataRemoverInterface
{
    public function removeUserData(Connection $connection): void
    {
        $connection->executeStatement(
            'TRUNCATE TABLE `nexicheckout_payment`'
        );
    }
}
