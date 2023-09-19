<?php
namespace Nets\Checkout\Service\Easy;

use Nets\Checkout\Core\Content\NetsPaymentApi\NetsPaymentEntity;
use Nets\Checkout\Service\ConfigService;
use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
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
use Symfony\Component\HttpFoundation\RequestStack;

class CheckoutService
{
    public const CHECKOUT_TYPE_EMBEDDED = 'embedded';

    public const CHECKOUT_TYPE_HOSTED = 'hosted';

    public const EASY_CHECKOUT_JS_ASSET_TEST = 'https://test.checkout.dibspayment.eu/v1/checkout.js?v=1';

    public const EASY_CHECKOUT_JS_ASSET_LIVE = 'https://checkout.dibspayment.eu/v1/checkout.js?v=1';

    public const NET_PRICE = 'net';

    /**
     * regexp for filtering strings
     */
    public const ALLOWED_CHARACTERS_PATTERN = '/[^\x{00A1}-\x{00AC}\x{00AE}-\x{00FF}\x{0100}-\x{017F}\x{0180}-\x{024F}\x{0250}-\x{02AF}\x{02B0}-\x{02FF}\x{0300}-\x{036F}A-Za-z0-9\!\#\$\%\(\)*\+\,\-\.\/\:\;\\=\?\@\[\]\\^\_\`\{\}\~ ]+/u';

    private EasyApiService $easyApiService;

    private ConfigService $configService;

    private EntityRepository $transactionRepository;

    private OrderTransactionStateHandler $transactionStateHandler;

    private CartService $cartService;

    private RequestStack $requestStack;

    private StateMachineRegistry $stateMachineRegistry;

    private EntityRepository $netsApiRepository;

    /**
     * CheckoutService constructor.
     */
    public function __construct(EasyApiService $easyApiService, ConfigService $configService, EntityRepository $transactionRepository, OrderTransactionStateHandler $orderTransactionStateHandler, CartService $cartService, RequestStack $requestStack, StateMachineRegistry $machineRegistry, EntityRepository $netsApiRepository)
    {
        $this->easyApiService          = $easyApiService;
        $this->configService           = $configService;
        $this->transactionRepository   = $transactionRepository;
        $this->transactionStateHandler = $orderTransactionStateHandler;
        $this->cartService             = $cartService;
        $this->requestStack            = $requestStack;
        $this->stateMachineRegistry    = $machineRegistry;
        $this->netsApiRepository       = $netsApiRepository;
    }

    /**
     * @param string $checkoutType
     *
     * @return string
     * @throws EasyApiException
     */
    public function createPayment(SalesChannelContext $salesChannelContext, $checkoutType = self::CHECKOUT_TYPE_EMBEDDED, AsyncPaymentTransactionStruct $transaction = null)
    {
        $environment = $this->configService->getEnvironment($salesChannelContext->getSalesChannel()
            ->getId());
        $secretKey = $this->configService->getSecretKey($salesChannelContext->getSalesChannel()
            ->getId());
        $this->easyApiService->setEnv($environment);
        $this->easyApiService->setAuthorizationKey($secretKey);
        $payload = json_encode($this->collectRequestParams($salesChannelContext, $checkoutType, $transaction));

        return $this->easyApiService->createPayment($payload);
    }

    /**
     * @param
     *            $amount
     */
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

    /**
     * @param
     *            $string
     */
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
     * @param
     *            $salesChannelContextId
     * @param
     *            $paymentId
     * @param
     *            $amount
     */
    public function chargePayment(OrderEntity $orderEntity, $salesChannelContextId, Context $context, $paymentId, $amount): array
    {
        $transaction = $orderEntity->getTransactions()->first();
        $environment = $this->configService->getEnvironment($salesChannelContextId);
        $secretKey   = $this->configService->getSecretKey($salesChannelContextId);
        $this->easyApiService->setEnv($environment);
        $this->easyApiService->setAuthorizationKey($secretKey);

        $payload = $this->getTransactionOrderItems($orderEntity, $amount);

        $chargeId = $this->easyApiService->chargePayment($paymentId, json_encode($payload));

        $chargeIdArr = json_decode($chargeId);
        $payment     = $this->easyApiService->getPayment($paymentId);

        if ($transaction->getStateMachineState()->getTechnicalName() != 'open') {
            $this->transactionStateHandler->reopen($transaction->getId(), $context);
        }

        $allChargeAmount = $payment->getChargedAmount();

        if ($this->prepareAmount($amount) == $payment->getOrderAmount() || $allChargeAmount == $payment->getOrderAmount()) {
            $this->transactionStateHandler->paid($transaction->getId(), $context);
        } else {
            $this->payPartially($transaction->getId(), $context);
        }

        // For inserting amount available respect to charge id

        $this->netsApiRepository->create([
            [
                'order_id'         => $payment->getOrderId() ? $payment->getOrderId() : '',
                'charge_id'        => $chargeIdArr->chargeId ? $chargeIdArr->chargeId : '',
                'operation_type'   => 'capture',
                'operation_amount' => $amount,
                'amount_available' => $amount,
            ],
        ], $context);

        return $payload;
    }

    /**
     * @param
     *            $salesChannelContextId
     * @param
     *            $paymentId
     * @param
     *            $amount
     *
     * @throws EasyApiException
     */
    public function refundPayment(OrderEntity $orderEntity, $salesChannelContextId, Context $context, $paymentId, $amount): array
    {
        $transaction = $orderEntity->getTransactions()->first();
        $environment = $this->configService->getEnvironment($salesChannelContextId);
        $secretKey   = $this->configService->getSecretKey($salesChannelContextId);
        $this->easyApiService->setEnv($environment);
        $this->easyApiService->setAuthorizationKey($secretKey);
        $payment  = $this->easyApiService->getPayment($paymentId);
        $chargeId = $payment->getFirstChargeId();
        $payload  = false;

        // Refund functionality
        $chargeArrWithAmountAvailable = [];
        $chargeIdArr                  = $payment->getAllCharges();
        $refundResult                 = false;
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
            $count = 0;
            foreach ($refundChargeIdArray as $key => $value) {
                // refund method
                $payload = $this->getTransactionOrderItems($orderEntity, $value);

                $refundResult = $this->easyApiService->refundPayment($key, json_encode($payload));

                // update table for amount available
                if ($refundResult) {
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

        if ($refundResult) {
            $payment = $this->easyApiService->getPayment($paymentId);

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

    /**
     * @param string $checkoutType
     */
    private function collectRequestParams(SalesChannelContext $salesChannelContext, $checkoutType = self::CHECKOUT_TYPE_EMBEDDED, AsyncPaymentTransactionStruct $transaction = null): array
    {
        if (is_object($transaction)) {
            $cartOrderEntityObject = $transaction->getOrder();
            $reference             = $cartOrderEntityObject->getOrderNumber();
            $amount                = $cartOrderEntityObject->getAmountTotal();
        } else {
            $cart                  = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);
            $cartOrderEntityObject = $cart;
            $amount                = $cart->getPrice()->getTotalPrice();
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
            $data['checkout']['cancelUrl'] = $this->requestStack->getCurrentRequest()->getUriForPath('/nets/order/cancel');
        }
        $data['checkout']['merchantTermsUrl'] = $this->configService->getMerchantTermsUrl($salesChannelContext->getSalesChannel()
            ->getId());
        $data['checkout']['termsUrl'] = $this->configService->getTermsAndConditionsUrl($salesChannelContext->getSalesChannel()
            ->getId());
        $chargeNow = $this->configService->getChargeNow($salesChannelContext->getSalesChannel()
            ->getId());

        if ($chargeNow == 'yes') {
            $data['checkout']['charge'] = 'true';
        }

        $data['checkout']['merchantHandlesConsumerData'] = true;

        if ($checkoutType == self::CHECKOUT_TYPE_HOSTED) {
            $data['checkout']['integrationType'] = 'HostedPaymentPage';
        }

        if ($checkoutType == self::CHECKOUT_TYPE_EMBEDDED) {
            $data['checkout']['url'] = $this->requestStack->getCurrentRequest()->getUriForPath('/nets/order/finish');
        }

        $countryIso = $salesChannelContext->getCustomer()
            ->getActiveShippingAddress()
            ->getCountry()
            ->getIso3();

        $prefix = '';

        if ($countryIso == 'DNK') {
            $prefix = '+45';
        } elseif ($countryIso == 'SWE') {
            $prefix = '+46';
        } elseif ($countryIso == 'USA') {
            $prefix = '+1';
        } elseif ($countryIso == 'NOR') {
            $prefix = '+47';
        } elseif ($countryIso == 'DEU') {
            $prefix = '+49';
        } elseif ($countryIso == 'FIN') {
            $prefix = '+358';
        } elseif ($countryIso == 'GBR') {
            $prefix = '+44';
        } elseif ($countryIso == 'FRA') {
            $prefix = '+33';
        } elseif ($countryIso == 'AUT') {
            $prefix = '+43';
        } elseif ($countryIso == 'NLD') {
            $prefix = '+31';
        } elseif ($countryIso == 'CHE') {
            $prefix = '+41';
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

        if ($phoneNumber) {
            $replace_array                               = ['/', '-', ' ', $prefix];
            $data['checkout']['consumer']['phoneNumber'] = [
                'prefix' => $prefix,
                'number' => str_replace($replace_array, '', $phoneNumber),
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
        foreach ($cartOrderEntityObject->getLineItems() as $item) {
            $taxes = $this->getRowTaxes($item->getPrice()
                ->getCalculatedTaxes());

            $taxPrice = 0;
            $quantity = $item->getQuantity();

            if ($cartOrderEntityObject instanceof Cart) {
                $ref_name = $item->getId();

                if (method_exists($item, 'getpayload')) {
                    $payload = $item->getpayload();

                    if (isset($payload['productNumber'])) {
                        $ref_name = $payload['productNumber'];
                    }
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
                    'name'             => $this->stringFilter($item->getLabel()),
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
                $product = $item->getUnitPrice();

                $ref_name = $item->getProductId() ?? $item->getId();

                if (method_exists($item, 'getpayload')) {
                    $payload = $item->getpayload();

                    if (isset($payload['productNumber'])) {
                        $ref_name = $payload['productNumber'];
                    }
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
                    'name'             => $this->stringFilter($item->getLabel()),
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

        $taxes = $this->getRowTaxes($shippingCost->getCalculatedTaxes());

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
     *            $context
     * @param array $fields
     */
    private function updateTransactionCustomFields(OrderTransactionEntity $transaction, $context, $fields = []): void
    {
        $customFields                              = $transaction->getCustomFields();
        $fields_arr                                = $customFields['nets_easy_payment_details'];
        $merged                                    = array_merge($fields_arr, $fields);
        $customFields['nets_easy_payment_details'] = $merged;
        $update                                    = [
            'id'           => $transaction->getId(),
            'customFields' => $customFields,
        ];
        $transaction->setCustomFields($customFields);
        $this->transactionRepository->update([
            $update,
        ], $context);
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
}
