<?php

declare(strict_types=1);

namespace Nexi\Checkout\Dictionary;

class OrderTransactionDictionary
{
    public const CUSTOM_FIELDS_PREFIX = 'customFields.';

    public const CUSTOM_FIELDS_NEXI_CHECKOUT_PAYMENT_ID = 'nexi_checkout_payment_id';

    public const CUSTOM_FIELDS_NEXI_CHECKOUT_ORDER = 'nexi_checkout_order';

    public const CUSTOM_FIELDS_NEXI_CHECKOUT_REFUNDED = 'nexi_checkout_refunded';
}
