<?php

declare(strict_types=1);

namespace NexiNets\Lifecycle;

use Doctrine\DBAL\Connection;

interface UserDataRemoverInterface
{
    public function removeUserData(Connection $connection): void;
}
