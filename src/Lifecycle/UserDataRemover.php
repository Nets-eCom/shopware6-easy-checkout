<?php

declare(strict_types=1);

namespace NexiNets\Lifecycle;

use Doctrine\DBAL\Connection;

final readonly class UserDataRemover implements UserDataRemoverInterface
{
    public function removeUserData(Connection $connection): void
    {
        $connection->executeStatement(
            'TRUNCATE TABLE `nexinets_payment`'
        );
    }
}
