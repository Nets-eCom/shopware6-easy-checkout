<?php

declare(strict_types=1);

namespace NexiNets\Entity\NexiNetsPaymentApi;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<NexiNetsPaymentEntity>
 *
 * @method void               add(NexiNetsPaymentEntity $entity)
 * @method void               set(string $key, NexiNetsPaymentEntity $entity)
 * @method NexiNetsPaymentEntity[]    getIterator()
 * @method NexiNetsPaymentEntity[]    getElements()
 * @method NexiNetsPaymentEntity|null get(string $key)
 * @method NexiNetsPaymentEntity|null first()
 * @method NexiNetsPaymentEntity|null last()
 */
class NexiNetsPaymentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NexiNetsPaymentEntity::class;
    }
}
