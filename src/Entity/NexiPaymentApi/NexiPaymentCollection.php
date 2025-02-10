<?php

declare(strict_types=1);

namespace Nexi\Checkout\Entity\NexiPaymentApi;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<NexiPaymentEntity>
 */
class NexiPaymentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NexiPaymentEntity::class;
    }
}
