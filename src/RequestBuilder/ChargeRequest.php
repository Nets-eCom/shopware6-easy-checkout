<?php

declare(strict_types=1);

namespace NexiNets\RequestBuilder;

use NexiNets\Administration\Model\ChargeData;
use NexiNets\CheckoutApi\Model\Request\FullCharge;
use NexiNets\CheckoutApi\Model\Request\PartialCharge;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\RequestBuilder\Helper\FormatHelper;
use NexiNets\RequestBuilder\PaymentRequest\ItemsBuilder;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

class ChargeRequest
{
    public function __construct(
        private readonly FormatHelper $helper,
        private readonly ItemsBuilder $itemsBuilder
    ) {
    }

    public function buildFullCharge(OrderTransactionEntity $transaction): FullCharge
    {
        return new FullCharge(
            $this->helper->priceToInt(
                $transaction->getAmount()->getTotalPrice()
            )
        );
    }

    public function buildPartialCharge(OrderTransactionEntity $transaction, ChargeData $chargeData): PartialCharge
    {
        if ($chargeData->getItems() === []) {
            return new PartialCharge(
                [$this->itemsBuilder->createUnrelatedPartialChargeItem(
                    $transaction,
                    $chargeData->getAmount()
                )],
                false
            );
        }

        $orderArray = $transaction->getCustomFieldsValue(OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_ORDER);

        return new PartialCharge(
            $this->itemsBuilder->createForCharge($chargeData, $orderArray)
        );
    }
}
