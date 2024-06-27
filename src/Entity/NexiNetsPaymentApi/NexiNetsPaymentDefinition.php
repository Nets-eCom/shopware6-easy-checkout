<?php

declare(strict_types=1);

namespace NexiNets\Entity\NexiNetsPaymentApi;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class NexiNetsPaymentDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'nexinets_payment';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return NexiNetsPaymentCollection::class;
    }

    public function getEntityClass(): string
    {
        return NexiNetsPaymentEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new StringField('order_id', 'orderId'),
            new StringField('order_id', 'orderId'),
            new StringField('charge_id', 'charge_id'),
        ]);
    }
}
