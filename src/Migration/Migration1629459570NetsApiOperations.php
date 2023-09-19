<?php

declare(strict_types=1);

namespace Nets\Checkout\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1629459570NetsApiOperations extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1_629_459_570;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $query = <<<SQL
        CREATE TABLE IF NOT EXISTS nets_payment_operations (
            id BINARY(16) NOT NULL PRIMARY KEY,
            order_id VARCHAR(250) NOT NULL,
            charge_id VARCHAR(250) NOT NULL,
            operation_type VARCHAR(50) NOT NULL,
            operation_amount VARCHAR(50) NOT NULL,
            amount_available VARCHAR(50) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
            ENGINE = InnoDB
            DEFAULT CHARSET = utf8mb4
            COLLATE = utf8mb4_unicode_ci;
        SQL;

        $connection->executeStatement($query);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
