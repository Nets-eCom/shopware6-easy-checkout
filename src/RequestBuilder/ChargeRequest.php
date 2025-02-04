<?php

declare(strict_types=1);

namespace Nexi\Checkout\RequestBuilder;

use Nexi\Checkout\Administration\Model\ChargeData;
use Nexi\Checkout\Dictionary\OrderTransactionDictionary;
use Nexi\Checkout\RequestBuilder\ChargeRequest\ItemsBuilder;
use Nexi\Checkout\RequestBuilder\Helper\FormatHelper;
use NexiCheckout\Model\Request\FullCharge;
use NexiCheckout\Model\Request\PartialCharge;
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
                $this->itemsBuilder->createUnrelatedPartialChargeItem(
                    $transaction,
                    $chargeData->getAmount()
                ),
                false
            );
        }

        $orderArray = $transaction->getCustomFieldsValue(OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_CHECKOUT_ORDER);

        return new PartialCharge(
            $this->itemsBuilder->createForCharge($chargeData, $orderArray)
        );
    }
}
