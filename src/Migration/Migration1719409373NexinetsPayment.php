<?php declare(strict_types=1);

namespace NexiNets\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1719409373NexinetsPayment extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1719409373;
    }

    public function update(Connection $connection): void
    {
        $query = <<<'SQL'
CREATE TABLE nexinets_payment
(
    id         BINARY(16) NOT NULL,
    order_id   VARCHAR(255) DEFAULT NULL,
    charge_id  VARCHAR(255) DEFAULT NULL,
    data       LONGTEXT     DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME     DEFAULT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;
SQL;

        $connection->executeStatement($query);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Add destructive update if necessary
    }
}
