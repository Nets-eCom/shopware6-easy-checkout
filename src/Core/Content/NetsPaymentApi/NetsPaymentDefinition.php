<?php declare(strict_types=1);
namespace Nets\Checkout\Core\Content\NetsPaymentApi;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;

class NetsPaymentDefinition extends EntityDefinition
{

    public const ENTITY_NAME = 'nets_payment_operations';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('order_id', 'order_id')),
            (new StringField('charge_id', 'charge_id')),
            (new StringField('operation_type', 'operation_type')),
            (new FloatField('operation_amount', 'operation_amount')),
            (new FloatField('amount_available', 'amount_available')),
            
        ]);
    }

    public function getCollectionClass(): string
    {
        return NetsPaymentCollection::class;
    }
    public function getEntityClass(): string
    {
        return NetsPaymentEntity::class;
    }
}