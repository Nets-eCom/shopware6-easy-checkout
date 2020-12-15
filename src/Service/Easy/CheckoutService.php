<?php

namespace Nets\Checkout\Service\Easy;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Nets\Checkout\Service\Easy\Api\EasyApiService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Nets\Checkout\Service\Easy\Api\Exception\EasyApiException;
use Nets\Checkout\Service\ConfigService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Symfony\Component\HttpFoundation\RequestStack;

class CheckoutService
{

    const CHECKOUT_TYPE_EMBEDDED = 'embedded';
    const CHECKOUT_TYPE_HOSTED = 'hosted';

    /**
     * @var EasyApiService
     */
    private $easyApiService;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var EntityRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var RequestStack
     */
    private $requestStack;


    /**
     * regexp for filtering strings
     */
    const ALLOWED_CHARACTERS_PATTERN = '/[^\x{00A1}-\x{00AC}\x{00AE}-\x{00FF}\x{0100}-\x{017F}\x{0180}-\x{024F}'
    . '\x{0250}-\x{02AF}\x{02B0}-\x{02FF}\x{0300}-\x{036F}'
    . 'A-Za-z0-9\!\#\$\%\(\)*\+\,\-\.\/\:\;\\=\?\@\[\]\\^\_\`\{\}\~ ]+/u';

    /**
     * CheckoutService constructor.
     * @param EasyApiService $easyApiService
     * @param ConfigService $configService
     * @param EntityRepositoryInterface $transactionRepository
     * @param OrderTransactionStateHandler $orderTransactionStateHandler
     * @param CartService $cartService
     * @param RequestStack $requestStack
     */
    public function __construct(EasyApiService $easyApiService,
                                ConfigService $configService,
                                EntityRepositoryInterface $transactionRepository,
                                OrderTransactionStateHandler $orderTransactionStateHandler,
                                CartService $cartService,
                                RequestStack $requestStack
)
    {
        $this->easyApiService = $easyApiService;
        $this->configService = $configService;
        $this->transactionRepository = $transactionRepository;
        $this->transactionStateHandler = $orderTransactionStateHandler;
        $this->cartService = $cartService;
        $this->requestStack = $requestStack;
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param SalesChannelContext $salesChannelContext
     * @param string $checkoutType
     * @return string
     * @throws EasyApiException
     */
    public function createPayment(SalesChannelContext $salesChannelContext, $checkoutType = self::CHECKOUT_TYPE_EMBEDDED, AsyncPaymentTransactionStruct $transaction = null) {
        $environment = $this->configService->getEnvironment($salesChannelContext->getSalesChannel()->getId());
        $secretKey = $this->configService->getSecretKey($salesChannelContext->getSalesChannel()->getId());
        $this->easyApiService->setEnv($environment);
        $this->easyApiService->setAuthorizationKey($secretKey);
        $payload = json_encode($this->collectRequestParams($salesChannelContext, $checkoutType, $transaction));
        return $this->easyApiService->createPayment($payload);
    }

    /**
     * @param $salesChannelContext
     * @return array[]
     */
    private function collectRequestParamsForEmbedded(SalesChannelContext $salesChannelContext) {
        $cart = $this->cartService->getCart($salesChannelContext->getToken(),$salesChannelContext);

        $data =  [
            'order' => [
                //'items' => $this->getOrderItemsForEmbedded($lineItemsCollection),
                'items' => $this->getOrderItemsTest( $cart),
                'amount' => $this->prepareAmount($cart->getPrice()->getTotalPrice()),
                'currency' => $salesChannelContext->getCurrency()->getIsoCode(),
                'reference' => $salesChannelContext->getToken(),
            ]];

        //$data['checkout']['returnUrl'] = $transaction->getReturnUrl();
        //$data['checkout']['integrationType'] = 'HostedPaymentPage';

        $data['checkout']['termsUrl'] = $this->configService->getTermsAndConditionsUrl($salesChannelContext->getSalesChannel()->getId());

        $data['checkout']['merchantHandlesConsumerData'] = true;

        $data['checkout']['url'] = $this->requestStack->getCurrentRequest()->getUriForPath('/checkout/confirm');

        $data['checkout']['consumer'] =
            ['email' =>  $salesChannelContext->getCustomer()->getEmail(),
                'privatePerson' => [
                    'firstName' => $this->stringFilter($salesChannelContext->getCustomer()->getFirstname()),
                    'lastName' => $this->stringFilter($salesChannelContext->getCustomer()->getLastname())]
            ];

        return $data;
    }

    /**
     * @param $lineItemsCollection
     * @return mixed
     */
    private function getOrderItemsForEmbedded($lineItemsCollection) {

        $items = [];

        foreach ($lineItemsCollection as $item) {
            $taxes = $this->getRowTaxes($item->getPrice()->getCalculatedTaxes());

            $items[] = [
                'reference' => $item->getId(),
                'name' => $this->stringFilter($item->getLabel()),
                'quantity' => $item->getQuantity(),
                'unit' => 'pcs',
                'unitPrice' => $this->prepareAmount($item->getPrice()->getUnitPrice()),
                'taxRate' => $this->prepareAmount($taxes['taxRate']),
                'taxAmount' => $this->prepareAmount($taxes['taxAmount']),
                'grossTotalAmount' => $this->prepareAmount($item->getPrice()->getTotalPrice()),
                'netTotalAmount' => $this->prepareAmount($item->getPrice()->getTotalPrice() - $taxes['taxAmount'])];
          }

          return $items;
    }

    /**
     * @param SalesChannelContext $salesChannelContext
     * @param AsyncPaymentTransactionStruct|null $transaction
     * @param string $checkoutType
     * @return array
     */
    private function collectRequestParams(SalesChannelContext $salesChannelContext, $checkoutType = self::CHECKOUT_TYPE_EMBEDDED, AsyncPaymentTransactionStruct $transaction = null)
    {
        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        $reference = $salesChannelContext->getToken();

        if(is_object( $transaction )) {
            $orderEntity = $transaction->getOrder();
            $reference = $orderEntity->getOrderNumber();
        }
        $data = [
            'order' => [
                'items' => $this->getOrderItemsTest($cart),
                'amount' => $this->prepareAmount($cart->getPrice()->getTotalPrice()),
                'currency' => $salesChannelContext->getCurrency()->getIsoCode(),
                'reference' => $reference,
            ]];

        if(is_object($transaction)) {
            $data['checkout']['returnUrl'] = $transaction->getReturnUrl();
        }

        if (self::CHECKOUT_TYPE_HOSTED == $checkoutType) {
            $data['checkout']['integrationType'] = 'HostedPaymentPage';
        }

        if(self::CHECKOUT_TYPE_EMBEDDED == $checkoutType) {
            $data['checkout']['url'] = $this->requestStack->getCurrentRequest()->getUriForPath('/checkout/confirm');
        }

        $data['checkout']['termsUrl'] = $this->configService->getTermsAndConditionsUrl($salesChannelContext->getSalesChannel()->getId());

        $data['checkout']['merchantHandlesConsumerData'] = true;

        $data['checkout']['consumer'] =
            ['email' =>  $salesChannelContext->getCustomer()->getEmail(),
                'privatePerson' => [
                    'firstName' => $this->stringFilter($salesChannelContext->getCustomer()->getFirstname()),
                    'lastName' => $this->stringFilter($salesChannelContext->getCustomer()->getLastname())]
            ];


        $data['notifications'] =
            ['webhooks' =>
                [
                    ['eventName' => 'payment.checkout.completed',
                        'url' => 'https://some-url.com',
                        'authorization' => substr(str_shuffle(MD5(microtime())), 0, 10)]
                  ]];

        return $data;
    }

    /**
     * @param OrderEntity $orderEntity
     * @return array
     */
    private function getOrderItems(OrderEntity $orderEntity) {
        $items = [];
        // Products
        foreach ($orderEntity->getLineItems() as $item) {
            $taxes = $this->getRowTaxes($item->getPrice()->getCalculatedTaxes());

        $items[] = [
                'reference' => $item->getProductId(),
                'name' => $this->stringFilter($item->getLabel()),
                'quantity' => $item->getQuantity(),
                'unit' => 'pcs',
                'unitPrice' => $this->prepareAmount($item->getUnitPrice() - $taxes['taxAmount']),
                'taxRate' => $this->prepareAmount($taxes['taxRate']),
                'taxAmount' => $this->prepareAmount($taxes['taxAmount']),
                'grossTotalAmount' => $this->prepareAmount($item->getTotalPrice()),
                'netTotalAmount' => $this->prepareAmount($item->getTotalPrice() - $taxes['taxAmount'])];
       }


        if($orderEntity->getShippingTotal()) {
            $items[] = $this->shippingCostLine();
        }


        return $items;
    }

    private function getOrderItemsTest(Cart $cart) {

        $collection = $cart->getLineItems();

        $items = [];

        foreach ($collection as $item) {
            $taxes = $this->getRowTaxes($item->getPrice()->getCalculatedTaxes());
            $items[] = [
                'reference' => $item->getId(),
                'name' => $this->stringFilter($item->getLabel()),
                'quantity' => $item->getQuantity(),
                'unit' => 'pcs',
                'unitPrice' => $this->prepareAmount($item->getPrice()->getUnitPrice() - $taxes['taxAmount']),
                'taxRate' => $this->prepareAmount($taxes['taxRate']),
                'taxAmount' => $this->prepareAmount($taxes['taxAmount']),
                'grossTotalAmount' => $this->prepareAmount($item->getPrice()->getTotalPrice()),
                'netTotalAmount' => $this->prepareAmount($item->getPrice()->getTotalPrice() - $taxes['taxAmount'])];
        }


        $shippingCost =  $cart->getShippingCosts();

        if($shippingCost->getTotalPrice() > 0) {
            $items[] = $this->shippingCostLine($shippingCost);
        }
        return $items;
    }

    /**
     * @param OrderEntity $orderEntity
     * @return array
     */
    public function getTransactionOrderItems(OrderEntity $orderEntity) {

        return ['amount' => $this->prepareAmount($orderEntity->getAmountTotal()),
         'orderItems' => $this->getOrderItems($orderEntity)];
    }

    /**
     * @param CalculatedTaxCollection $calculatedTaxCollection
     * @return array
     */
    private function getRowTaxes(CalculatedTaxCollection $calculatedTaxCollection) {
        $taxAmount = 0;
        $taxRate = 0;
        foreach($calculatedTaxCollection as $calculatedTax) {
            $taxRate += $calculatedTax->getTaxRate();
            $taxAmount += $calculatedTax->getTax();
        }
        return ['taxRate' => $taxRate,
                'taxAmount' => $taxAmount];
    }

    /**
     * @param CalculatedPrice $cost
     * @return array
     */
    private function shippingCostLine(CalculatedPrice $cost) {
        return [
            'reference' => 'shipping',
            'name' => 'Shipping',
            'quantity' => 1,
            'unit' => 'pcs',
            'unitPrice' => $this->prepareAmount($cost->getTotalPrice()),
            'taxRate' => 0,
            'taxAmount' => 0,
            'grossTotalAmount' => $this->prepareAmount($cost->getTotalPrice()),
            'netTotalAmount' => $this->prepareAmount( $cost->getTotalPrice() )
        ];
    }

    /**
     * @param $amount
     * @return int
     */
    private function prepareAmount($amount = 0) {
        return (int)round($amount * 100);
    }

    /**
     * @param $string
     * @return string
     */
    public function stringFilter($string = '') {
        $string = substr($string, 0, 128);
        return preg_replace(self::ALLOWED_CHARACTERS_PATTERN, '', $string);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param $salesChannelContextId
     * @param $paymentId
     * @return false|string
     */
    public function chargePayment(OrderEntity $orderEntity, $salesChannelContextId, \Shopware\Core\Framework\Context $context, $paymentId) {
        $transaction = $orderEntity->getTransactions()->first();
        $environment = $this->configService->getEnvironment($salesChannelContextId);
        $secretKey = $this->configService->getSecretKey($salesChannelContextId);
        $this->easyApiService->setEnv($environment);
        $this->easyApiService->setAuthorizationKey($secretKey);
        $payload = json_encode($this->getTransactionOrderItems($orderEntity));
        $this->easyApiService->chargePayment($paymentId, $payload);
        $this->updateTransactionCustomFields($transaction, $context, ['can_capture' => false, 'can_refund' => true]);
        $this->transactionStateHandler->pay($transaction->getId(), $context);
        return $payload;
    }

    /**
     * @param OrderEntity $orderEntity
     * @param $salesChannelContextId
     * @param $chargeId
     * @return false|string
     * @throws EasyApiException
     */
    public function refundPayment(OrderEntity $orderEntity, $salesChannelContextId, \Shopware\Core\Framework\Context $context, $chargeId) {
        $transaction = $orderEntity->getTransactions()->first();
        $environment = $this->configService->getEnvironment($salesChannelContextId);
        $secretKey = $this->configService->getSecretKey($salesChannelContextId);
        $this->easyApiService->setEnv($environment);
        $this->easyApiService->setAuthorizationKey($secretKey);
        $payment = $this->easyApiService->getPayment($chargeId);
        $chargeId = $payment->getFirstChargeId();
        $payload = json_encode($this->getTransactionOrderItems($orderEntity));
        $this->easyApiService->refundPayment($chargeId, $payload);
        $this->updateTransactionCustomFields($transaction, $context, ['can_refund' => false]);
        $this->transactionStateHandler->refund($transaction->getId(), $context);
        return $payload;
    }

    /**
     * @param OrderTransactionEntity $transaction
     * @param $context
     * @param array $fields
     */
    private function updateTransactionCustomFields(OrderTransactionEntity $transaction, $context ,$fields = []) {
        $customFields = $transaction->getCustomFields();
        $fields_arr = $customFields['nets_easy_payment_details'];
        $merged = array_merge($fields_arr, $fields);
        $customFields['nets_easy_payment_details'] = $merged;
        $update = [
            'id'           => $transaction->getId(),
            'customFields' => $customFields,
        ];
        $transaction->setCustomFields($customFields);
        $this->transactionRepository->update([$update], $context);
    }
}
