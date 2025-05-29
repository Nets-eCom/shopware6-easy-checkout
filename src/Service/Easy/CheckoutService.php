<?php

namespace Nets\Checkout\Service\Easy;

use Nets\Checkout\Core\Content\NetsPaymentApi\NetsPaymentEntity;
use Nets\Checkout\Dictionary\CountryPhonePrefixDictionary;
use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Swag\CustomizedProducts\Core\Checkout\CustomizedProductsCartDataCollector;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class CheckoutService
{
    public const CHECKOUT_TYPE_EMBEDDED = 'embedded';

    public const CHECKOUT_TYPE_HOSTED = 'hosted';

    public const EASY_CHECKOUT_JS_ASSET_LIVE = 'https://checkout.dibspayment.eu/v1/checkout.js?v=1';

    public const EASY_CHECKOUT_JS_ASSET_TEST = 'https://test.checkout.dibspayment.eu/v1/checkout.js?v=1';

    public const NET_PRICE = 'net';

    /**
     * regexp for filtering strings
     */
    public const ALLOWED_CHARACTERS_PATTERN = '/[^\x{00A1}-\x{00AC}\x{00AE}-\x{00FF}\x{0100}-\x{017F}\x{0180}-\x{024F}\x{0250}-\x{02AF}\x{02B0}-\x{02FF}\x{0300}-\x{036F}A-Za-z0-9\!\#\$\%\(\)*\+\,\-\.\/\:\;\\=\?\@\[\]\\^\_\`\{\}\~ ]+/u';

    private EasyApiService $easyApiService;

    private ConfigService $configService;

    private OrderTransactionStateHandler $transactionStateHandler;

    private CartService $cartService;

    private RequestStack $requestStack;

    private StateMachineRegistry $stateMachineRegistry;

    private EntityRepository $netsApiRepository;

    private RouterInterface $router;

    public function __construct(
        EasyApiService $easyApiService,
        ConfigService $configService,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        CartService $cartService,
        RequestStack $requestStack,
        StateMachineRegistry $machineRegistry,
        EntityRepository $netsApiRepository,
        RouterInterface $router
    ) {
        $this->easyApiService = $easyApiService;
        $this->configService = $configService;
        $this->transactionStateHandler = $orderTransactionStateHandler;
        $this->cartService = $cartService;
        $this->requestStack = $requestStack;
        $this->stateMachineRegistry = $machineRegistry;
        $this->netsApiRepository = $netsApiRepository;
        $this->router = $router;
    }

    /**
     * @throws EasyApiException
     */
    public function createPayment(SalesChannelContext $salesChannelContext, $checkoutType = self::CHECKOUT_TYPE_EMBEDDED, AsyncPaymentTransactionStruct $transaction = null): ?string
    {
        $payload = json_encode($this->collectRequestParams($salesChannelContext, $checkoutType, $transaction));

        return $this->easyApiService->createPayment($payload, $salesChannelContext->getSalesChannelId());
    }

    public function getTransactionOrderItems(OrderEntity $orderEntity, $amount): array
    {
        if ($amount == $orderEntity->getAmountTotal()) {
            $orderItems = $this->getOrderItems($orderEntity);
        } else {
            $orderItems = $this->getDummyOrderItem($this->prepareAmount($amount));
        }

        return [
            'amount'     => $this->prepareAmount($amount),
            'orderItems' => $orderItems,
        ];
    }

    public function stringFilter($string = ''): string
    {
        $string = substr($string, 0, 128);
        $name   = preg_replace(self::ALLOWED_CHARACTERS_PATTERN, '', $string);

        if (empty($name)) {
            return preg_replace('/[^A-Za-z0-9() -]/', '', $string);
        }

        return $name;
    }

    /**
     * @throws EasyApiException
     */
    public function chargePayment(OrderEntity $orderEntity, Context $context, $paymentId, $amount): array
    {
        $transaction = $orderEntity->getTransactions()->first();
        $salesChanelId = $orderEntity->getSalesChannelId();

        $payload = $this->getTransactionOrderItems($orderEntity, $amount);

        $chargeId = $this->easyApiService->chargePayment($paymentId, json_encode($payload), $salesChanelId);

        $chargeIdArr = json_decode($chargeId);
        $payment     = $this->easyApiService->getPayment($paymentId, $salesChanelId);

        $allChargeAmount = $payment->getChargedAmount();

        if ($this->prepareAmount($amount) == $payment->getOrderAmount() || $allChargeAmount == $payment->getOrderAmount()) {
            $this->transactionStateHandler->paid($transaction->getId(), $context);
        } else {
            $this->payPartially($transaction->getId(), $context);
        }

        // For inserting amount available respect to charge id

        $this->netsApiRepository->create([
            [
                'order_id'         => $payment->getOrderId() ?: '',
                'charge_id'        => $chargeIdArr->chargeId ?: '',
                'operation_type'   => 'capture',
                'operation_amount' => $amount,
                'amount_available' => $amount,
            ],
        ], $context);

        return $payload;
    }

    /**
     * @throws EasyApiException
     */
    public function refundPayment(OrderEntity $orderEntity, Context $context, $paymentId, $amount): array
    {
        $transaction = $orderEntity->getTransactions()->first();
        $salesChanelId = $orderEntity->getSalesChannelId();
        $payment  = $this->easyApiService->getPayment($paymentId, $salesChanelId);
        $payload  = [];

        // Refund functionality
        $chargeArrWithAmountAvailable = [];
        $chargeIdArr                  = $payment->getAllCharges();
        $refundResult                 = null;
        foreach ($chargeIdArr as $row) {
            // select query based on charge to get amount available
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('charge_id', $row->chargeId));
            /** @var null|NetsPaymentEntity $result */
            $result = $this->netsApiRepository->search($criteria, $context)->first();

            if ($result) {
                $chargeArrWithAmountAvailable[$row->chargeId] = $result->getAvailableAmt();
            }
        }
        array_multisort($chargeArrWithAmountAvailable, SORT_DESC);
        // second block
        $amountToRefund      = $amount;
        $refundChargeIdArray = [];
        foreach ($chargeArrWithAmountAvailable as $key => $value) {
            if ($amountToRefund <= $value) {
                $refundChargeIdArray[$key] = $amountToRefund;

                break;
            }

            if ($amount >= $value) {
                $amount                    = $amount - $value;
                $refundChargeIdArray[$key] = $value;
            } else {
                $refundChargeIdArray[$key] = $amount;
            }

            if (array_sum($refundChargeIdArray) == $amountToRefund) {
                break;
            }
        }

        // third block
        if ($amountToRefund <= array_sum($refundChargeIdArray)) {
            foreach ($refundChargeIdArray as $key => $value) {
                // refund method
                $payload = $this->getTransactionOrderItems($orderEntity, $value);

                $refundResult = $this->easyApiService->refundPayment($key, json_encode($payload), $salesChanelId);

                // update table for amount available
                if ($refundResult !== null) {
                    // get amount available based on charge id

                    $criteria = new Criteria();
                    $criteria->addFilter(new EqualsFilter('charge_id', $key));
                    /** @var null|NetsPaymentEntity $result */
                    $result = $this->netsApiRepository->search($criteria, $context)->first();

                    if ($result) {
                        $availableAmount = $result->getAvailableAmt() - $value;

                        $update = [
                            'id'               => $result->getId(),
                            'amount_available' => $availableAmount,
                        ];

                        $this->netsApiRepository->update([
                            $update,
                        ], $context);
                    }
                }
            }
        }
        // End of refund
        $allRefundAmount = $payment->getRefundedAmount();

        if ($refundResult !== null) {
            $payment = $this->easyApiService->getPayment($paymentId, $salesChanelId);

            if ($this->prepareAmount($amountToRefund) == $payment->getOrderAmount() || $allRefundAmount == $payment->getOrderAmount()) {
                $this->transactionStateHandler->refund($transaction->getId(), $context);
            } else {
                if ($transaction->getStateMachineState()->getTechnicalName() == 'refunded_partially') {
                    $this->transactionStateHandler->reopen($transaction->getId(), $context);
                    $this->payPartially($transaction->getId(), $context);
                }
                $this->transactionStateHandler->refundPartially($transaction->getId(), $context);
            }
        }

        return $payload;
    }

    private function collectRequestParams(SalesChannelContext $salesChannelContext, string $checkoutType = self::CHECKOUT_TYPE_EMBEDDED, AsyncPaymentTransactionStruct $transaction = null): array
    {
        if (is_object($transaction)) {
            $cartOrderEntityObject = $transaction->getOrder();
            $reference             = $cartOrderEntityObject->getOrderNumber();
        } else {
            $cart                  = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);
            $cartOrderEntityObject = $cart;
            $reference             = $salesChannelContext->getToken();
        }

        $orderItems       = $this->getOrderItems($cartOrderEntityObject, $salesChannelContext);
        $grossTotalAmount = $orderItems['sumAmount'];
        unset($orderItems['sumAmount']);
        $data = [
            'order' => [
                'items'     => $orderItems,
                'amount'    => $grossTotalAmount,
                'currency'  => $salesChannelContext->getCurrency()->getIsoCode(),
                'reference' => $reference,
            ],
        ];

        if (is_object($transaction)) {
            $cartOrderEntityObject = $transaction->getOrder();
            $session               = $this->requestStack->getCurrentRequest()->getSession();
            $session->set('cancelOrderId', $cartOrderEntityObject->getOrderNumber());
            $session->set('sw_order_id', $cartOrderEntityObject->getId());
            $data['checkout']['returnUrl'] = $transaction->getReturnUrl();
            $data['checkout']['cancelUrl'] = $this->generateCancelUrl();
        }
        $data['checkout']['merchantTermsUrl'] = $this->configService->getMerchantTermsUrl();
        $data['checkout']['termsUrl'] = $this->configService->getTermsAndConditionsUrl();
        $chargeNow = $this->configService->getChargeNow();

        if ($chargeNow == 'yes') {
            $data['checkout']['charge'] = 'true';
        }

        $data['checkout']['merchantHandlesConsumerData'] = true;

        if ($checkoutType == self::CHECKOUT_TYPE_HOSTED) {
            $data['checkout']['integrationType'] = 'HostedPaymentPage';
        }

        if ($checkoutType == self::CHECKOUT_TYPE_EMBEDDED) {
            $data['checkout']['url'] = $this->router->generate('frontend.checkout.confirm.page', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $data['checkout']['consumer'] = [
            'email'           => $salesChannelContext->getCustomer()->getEmail(),
            'shippingAddress' => [
                'addressLine1' => $salesChannelContext->getCustomer()
                    ->getActiveShippingAddress()
                    ->getStreet(),
                'addressLine2' => $salesChannelContext->getCustomer()
                    ->getActiveShippingAddress()
                    ->getStreet(),
                'postalCode' => $salesChannelContext->getCustomer()
                    ->getActiveShippingAddress()
                    ->getZipcode(),
                'city' => $salesChannelContext->getCustomer()
                    ->getActiveShippingAddress()
                    ->getCity(),
                'country' => $salesChannelContext->getCustomer()
                    ->getActiveShippingAddress()
                    ->getCountry()
                    ->getIso3(),
            ],
        ];

        $phoneNumber = $salesChannelContext->getCustomer()
            ->getActiveShippingAddress()
            ->getPhoneNumber();

        if ($phoneNumber !== null) {
            $prefix = $this->getCountryPrefixByIso(
                $salesChannelContext->getCustomer()->getActiveShippingAddress()->getCountry()->getIso3()
            );

            $data['checkout']['consumer']['phoneNumber'] = [
                'prefix' => $prefix,
                'number' => str_replace(['/', '-', ' ', $prefix], '', $phoneNumber),
            ];
        }

        if (!empty($salesChannelContext->getCustomer()
            ->getActiveBillingAddress()
            ->getCompany())) {
            $data['checkout']['consumer']['company'] = [
                'name' => $salesChannelContext->getCustomer()
                    ->getActiveBillingAddress()
                    ->getCompany(),
                'contact' => [
                    'firstName' => $this->stringFilter($salesChannelContext->getCustomer()
                        ->getFirstname()),
                    'lastName' => $this->stringFilter($salesChannelContext->getCustomer()
                        ->getLastname()),
                ],
            ];
        } else {
            $data['checkout']['consumer']['privatePerson'] = [
                'firstName' => $this->stringFilter($salesChannelContext->getCustomer()
                    ->getFirstname()),
                'lastName' => $this->stringFilter($salesChannelContext->getCustomer()
                    ->getLastname()),
            ];
        }

        return $data;
    }

    /**
     * @param LineItem|OrderLineItemEntity $item
     */
    private function isItemFromCustomProductExtension(Struct $item): bool
    {
        if (!class_exists(CustomizedProductsCartDataCollector::class)) {
            return false;
        }

        return $item->getType() === CustomizedProductsCartDataCollector::CUSTOMIZED_PRODUCTS_TEMPLATE_LINE_ITEM_TYPE;
    }

    /**
     * @param LineItem|OrderLineItemEntity $item
     */
    private function getReferenceName(Struct $item): string
    {
        $defaultReturn = $item->getId();
        if ($item instanceof OrderLineItemEntity) {
            $defaultReturn = $item->getProductId() ?? $item->getId();
        }

        if (!method_exists($item, 'getPayload')) {
            return $defaultReturn;
        }

        $payload = $item->getPayload();
        if (isset($payload['productNumber'])) {
            return $payload['productNumber'];
        }

        return $defaultReturn;
    }

    /**
     * @param Cart|OrderEntity $cartOrderEntityObject
     */
    private function getOrderItems(Struct $cartOrderEntityObject, SalesChannelContext $salesChannelContext = null): array
    {
        $display_gross = true;

        if (empty($salesChannelContext)) {
            if ($cartOrderEntityObject->getTaxStatus() == self::NET_PRICE) {
                $display_gross = false;
            }
        } else {
            $display_gross = $salesChannelContext->getCurrentCustomerGroup()->getDisplayGross();
        }

        $items     = [];
        $sumAmount = 0;
        $parentIds = [];

        foreach ($cartOrderEntityObject->getLineItems() as $item) {

            $taxes = $this->getRowTaxes($item->getPrice()
                ->getCalculatedTaxes());

            $quantity = $item->getQuantity();

            if ($cartOrderEntityObject instanceof Cart) {

                $ref_name = $this->getReferenceName($item);
                $name = $this->stringFilter($item->getLabel());

                if ($this->isItemFromCustomProductExtension($item)) {
                    $customItem = $item->getChildren()->firstWhere(fn(LineItem $lineItem) => $lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE);
                    $ref_name = $this->getReferenceName($customItem);
                    $name = $customItem->getLabel();
                }

                $product = $item->getPrice()->getUnitPrice();

                if ($display_gross) {
                    $taxFormat   = '1' . str_pad(number_format((float) $taxes['taxRate'], 2, '.', ''), 5, '0', STR_PAD_LEFT);
                    $unitPrice   = round(round(($product * 100) / $taxFormat, 2) * 100);
                    $grossAmount = round($quantity * ($product * 100));
                } else {
                    $unitPrice   = $this->prepareAmount($product);
                    $taxPrice    = $taxes['taxAmount'] * 100;
                    $grossAmount = round($quantity * ($product * 100)) + $taxPrice;
                }

                $netAmount = round($quantity * $unitPrice);

                $items[] = [
                    'reference'        => $ref_name,
                    'name'             => $name,
                    'quantity'         => $quantity,
                    'unit'             => 'pcs',
                    'unitPrice'        => $unitPrice,
                    'taxRate'          => $this->prepareAmount($taxes['taxRate']),
                    'taxAmount'        => $grossAmount - $netAmount,
                    'grossTotalAmount' => $grossAmount,
                    'netTotalAmount'   => $netAmount,
                ];

                $sumAmount = $sumAmount + $grossAmount;
            }

            if ($cartOrderEntityObject instanceof OrderEntity) {
                if ($item->getParentId() === null && $this->isItemFromCustomProductExtension($item)) {
                    $parentIds[] = $item->getId();
                }

                if (in_array($item->getParentId(), $parentIds)) {
                    continue;
                }

                $product = $item->getUnitPrice();

                $ref_name = $this->getReferenceName($item);
                $name = $this->stringFilter($item->getLabel());

                if ($this->isItemFromCustomProductExtension($item)) {
                    $customItem = $cartOrderEntityObject->getLineItems()->firstWhere(fn(OrderLineItemEntity $lineItem) => $lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE && $lineItem->getParentId() === $item->getId());
                    $ref_name = $this->getReferenceName($customItem);
                    $name = $customItem->getLabel();
                }

                if ($display_gross) {
                    $taxFormat   = '1' . str_pad(number_format((float) $taxes['taxRate'], 2, '.', ''), 5, '0', STR_PAD_LEFT);
                    $unitPrice   = round(round(($product * 100) / $taxFormat, 2) * 100);
                    $grossAmount = round($quantity * ($product * 100));
                } else {
                    $unitPrice   = $this->prepareAmount($product);
                    $taxPrice    = $taxes['taxAmount'] * 100;
                    $grossAmount = round($quantity * ($product * 100)) + $taxPrice;
                }

                $netAmount = round($quantity * $unitPrice);

                $items[] = [
                    'reference'        => $ref_name,
                    'name'             => $name,
                    'quantity'         => $quantity,
                    'unit'             => 'pcs',
                    'unitPrice'        => $unitPrice,
                    'taxRate'          => $this->prepareAmount($taxes['taxRate']),
                    'taxAmount'        => $grossAmount - $netAmount,
                    'grossTotalAmount' => $grossAmount,
                    'netTotalAmount'   => $netAmount,
                ];

                $sumAmount = $sumAmount + $grossAmount;
            }
        }
        $shippingCost = $cartOrderEntityObject->getShippingCosts();

        $shipItems = $this->shippingCostLine($shippingCost, $display_gross);

        if ($shippingCost->getTotalPrice() > 0) {
            $items[] = $shipItems;
            $sumAmount += $shipItems['grossTotalAmount'];
        }

        if (!empty($salesChannelContext)) {
            $items['sumAmount'] = $sumAmount;
        }

        return $items;
    }

    private function getCountryPrefixByIso(string $iso): string
    {
        return CountryPhonePrefixDictionary::getPrefix($iso);
    }

    private function getRowTaxes(CalculatedTaxCollection $calculatedTaxCollection): array
    {
        $taxAmount = 0;
        $taxRate   = 0;
        foreach ($calculatedTaxCollection as $calculatedTax) {
            $taxRate += $calculatedTax->getTaxRate();
            $taxAmount += $calculatedTax->getTax();
        }

        return [
            'taxRate'   => $taxRate,
            'taxAmount' => $taxAmount,
        ];
    }

    private function shippingCostLine(CalculatedPrice $cost, $gross): array
    {
        $taxes = $this->getRowTaxes($cost->getCalculatedTaxes());

        $product  = $cost->getTotalPrice();
        $quantity = 1;

        if ($gross) {
            $taxFormat   = '1' . str_pad(number_format((float) $taxes['taxRate'], 2, '.', ''), 5, '0', STR_PAD_LEFT);
            $unitPrice   = round(round(($product * 100) / $taxFormat, 2) * 100);
            $grossAmount = round($quantity * ($product * 100));
        } else {
            $unitPrice   = $this->prepareAmount($product);
            $taxPrice    = $taxes['taxAmount'] * 100;
            $grossAmount = round($quantity * ($product * 100)) + $taxPrice;
        }
        $netAmount = round($quantity * $unitPrice);

        return [
            'reference'        => 'shipping',
            'name'             => 'Shipping',
            'quantity'         => 1,
            'unit'             => 'pcs',
            'unitPrice'        => $unitPrice,
            'taxRate'          => $this->prepareAmount($taxes['taxRate']),
            'taxAmount'        => $this->prepareAmount($taxes['taxAmount']),
            'grossTotalAmount' => $grossAmount,
            'netTotalAmount'   => $netAmount,
        ];
    }

    /**
     * @param
     *            $amount
     */
    private function prepareAmount($amount = 0): int
    {
        return (int) round($amount * 100);
    }

    /**
     * @param
     *            $amount
     */
    private function getDummyOrderItem($amount): array
    {
        $items = [];
        // Products
        $ref     = 'item' . rand(1, 100);
        $items[] = [
            'reference'        => $ref,
            'name'             => $ref,
            'quantity'         => 1,
            'unit'             => 'pcs',
            'unitPrice'        => $amount,
            'taxRate'          => 0,
            'taxAmount'        => 0,
            'grossTotalAmount' => $amount,
            'netTotalAmount'   => $amount,
        ];

        return $items;
    }

    private function payPartially(string $transactionId, Context $context): void
    {
        $this->stateMachineRegistry->transition(new Transition(OrderTransactionDefinition::ENTITY_NAME, $transactionId, StateMachineTransitionActions::ACTION_PAID_PARTIALLY, 'stateId'), $context);
    }

    public function generateCancelUrl(): string
    {
        return $this->router->generate('frontend.account.order.page', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
