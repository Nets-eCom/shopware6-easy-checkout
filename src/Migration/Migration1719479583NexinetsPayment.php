<?php declare(strict_types=1);

namespace NexiNets\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1719479583NexinetsPayment extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1719479583;
    }

    public function update(Connection $connection): void
    {
        $query = <<<'SQL'
CREATE TABLE IF NOT EXISTS nexinets_payment
(
    id         BINARY(16) NOT NULL,
    order_id   VARCHAR(255) DEFAULT NULL,
    charge_id  VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME     DEFAULT NULL,
    PRIMARY KEY (id)
)
    DEFAULT CHARACTER SET utf8
    COLLATE `utf8_unicode_ci`
    ENGINE = InnoDB;
SQL;

        $connection->executeStatement($query);
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `nexinets_payment`');
    }
}
