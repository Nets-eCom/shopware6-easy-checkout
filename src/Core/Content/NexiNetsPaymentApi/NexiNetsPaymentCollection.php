<?php

declare(strict_types=1);

namespace NexiNets\Core\Content\NexiNetsPaymentApi;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void               add(ExampleEntity $entity)
 * @method void               set(string $key, ExampleEntity $entity)
 * @method ExampleEntity[]    getIterator()
 * @method ExampleEntity[]    getElements()
 * @method null|ExampleEntity get(string $key)
 * @method null|ExampleEntity first()
 * @method null|ExampleEntity last()
 */

class NexiNetsPaymentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NexiNetsPaymentEntity::class;
    }
}