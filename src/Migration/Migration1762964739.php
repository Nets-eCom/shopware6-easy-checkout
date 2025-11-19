<?php

declare(strict_types=1);

namespace Nexi\Checkout\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1762964739 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1762964739;
    }

    public function update(Connection $connection): void
    {
        $oldPluginInstalled = $connection->fetchOne(
            'SELECT id FROM plugin WHERE name = :pluginName',
            [
                'pluginName' => 'NexiCheckout',
            ]
        );

        if (!$oldPluginInstalled) {
            return;
        }

        $sql = <<<SQL
            DELETE FROM system_config WHERE configuration_key LIKE 'NetsNexiCheckout.%';

            UPDATE system_config
            SET 
                configuration_key = REPLACE(configuration_key, 'NexiCheckout.config.', 'NetsNexiCheckout.config.'), 
                updated_at = NOW() 
            WHERE configuration_key LIKE 'NexiCheckout.config.%';
        SQL;

        $connection->executeStatement($sql);
    }
}
