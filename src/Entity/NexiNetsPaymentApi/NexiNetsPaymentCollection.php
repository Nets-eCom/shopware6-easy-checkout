<?php

declare(strict_types=1);

namespace NexiNets\Entity\NexiNetsPaymentApi;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<NexiNetsPaymentEntity>
 */
class NexiNetsPaymentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NexiNetsPaymentEntity::class;
    }
}
