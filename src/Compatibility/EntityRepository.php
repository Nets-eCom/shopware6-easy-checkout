<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

// TODO: Remove me if compatibility is at least 6.5.0.0
if (!class_exists('Shopware\Core\Framework\DataAbstractionLayer\EntityRepository')) {
    class EntityRepository implements EntityRepositoryInterface
    {
    }
}
