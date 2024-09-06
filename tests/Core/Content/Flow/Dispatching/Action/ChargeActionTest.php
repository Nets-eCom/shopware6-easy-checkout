<?php declare(strict_types=1);

namespace NexiNets\Tests\Core\Content\Flow\Dispatching\Action;

use NexiNets\CheckoutApi\Api\PaymentApi;
use NexiNets\CheckoutApi\Factory\PaymentApiFactory;
use NexiNets\CheckoutApi\Model\Request\FullCharge;
use NexiNets\CheckoutApi\Model\Result\ChargeResult;
use NexiNets\CheckoutApi\Model\Result\RetrievePaymentResult;
use NexiNets\Configuration\ConfigurationProvider;
use NexiNets\Core\Content\Flow\Dispatching\Action\ChargeAction;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\RequestBuilder\ChargeRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\OrderAware;

final class ChargeActionTest extends TestCase
{
    public function testHandleFlowShouldCharge(): void
    {
        $order = $this->createOrderEntity();
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction_uuid');
        $transaction->setCustomFields([
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID => '025400006091b1ef6937598058c4e487',
        ]);
        $order->getTransactions()->add($transaction);

        $configurationProvider = $this->createConfigurationProviderMock();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(false);

        $paymentApi = $this->createMock(PaymentApi::class);
        $paymentApi->expects($this->once())
            ->method('retrievePayment')
            ->with('025400006091b1ef6937598058c4e487')
            ->willReturn($this->createNotChargedRetrievePaymentResult());
        $paymentApiFactory = $this->createPaymentApiFactoryMock($paymentApi);

        $charge = new FullCharge(100);
        $chargeRequestBuilder = $this->createMock(ChargeRequest::class);
        $chargeRequestBuilder->expects($this->once())
            ->method('buildFullCharge')
            ->with($transaction)
            ->willReturn($charge);

        $paymentApi->expects($this->once())
            ->method('charge')
            ->with('025400006091b1ef6937598058c4e487', $charge)
            ->willReturn(new ChargeResult('test_charge_id'));

        $sut = new ChargeAction(
            $configurationProvider,
            $chargeRequestBuilder,
            $paymentApiFactory
        );

        $sut->handleFlow(
            $this->createStorableFlow($order)
        );
    }

    public function testHandleFlowShouldNotChargeIfAutoChargeEnabled(): void
    {
        $order = $this->createOrderEntity();
        $configurationProvider = $this->createConfigurationProviderMock();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(false);

        $paymentApi = $this->createMock(PaymentApi::class);
        $paymentApiFactory = $this->createPaymentApiFactoryMock($paymentApi);

        $chargeRequestBuilder = $this->createMock(ChargeRequest::class);

        $paymentApi->expects($this->never())
            ->method('charge');

        $sut = new ChargeAction(
            $configurationProvider,
            $chargeRequestBuilder,
            $paymentApiFactory
        );

        $sut->handleFlow(
            $this->createStorableFlow($order)
        );
    }

    public function testHandleFlowShouldNotChargeIfNotNexiPayment(): void
    {
        $order = $this->createOrderEntity();
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction_uuid');
        $order->getTransactions()->add($transaction);

        $configurationProvider = $this->createConfigurationProviderMock();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(false);

        $paymentApi = $this->createMock(PaymentApi::class);
        $paymentApi->expects($this->never())
            ->method('retrievePayment');
        $paymentApiFactory = $this->createPaymentApiFactoryMock($paymentApi);

        $chargeRequestBuilder = $this->createMock(ChargeRequest::class);

        $paymentApi->expects($this->never())
            ->method('charge');

        $sut = new ChargeAction(
            $configurationProvider,
            $chargeRequestBuilder,
            $paymentApiFactory
        );

        $sut->handleFlow(
            $this->createStorableFlow($order)
        );
    }

    public function testHandleFlowShouldNotChargeIfPaymentAlreadyCharged(): void
    {
        $order = $this->createOrderEntity();
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction_uuid');
        $transaction->setCustomFields([
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID => '025400006091b1ef6937598058c4e487',
        ]);
        $order->getTransactions()->add($transaction);

        $configurationProvider = $this->createConfigurationProviderMock();
        $configurationProvider
            ->method('isAutoCharge')
            ->with('test_sales_channel_id')
            ->willReturn(false);

        $paymentApi = $this->createMock(PaymentApi::class);
        $paymentApi->expects($this->once())
            ->method('retrievePayment')
            ->with('025400006091b1ef6937598058c4e487')
            ->willReturn($this->createAlreadyChargedRetrievePaymentResult());
        $paymentApiFactory = $this->createPaymentApiFactoryMock($paymentApi);

        $chargeRequestBuilder = $this->createMock(ChargeRequest::class);
        $chargeRequestBuilder->expects($this->never())
            ->method('buildFullCharge');

        $paymentApi->expects($this->never())
            ->method('charge');

        $sut = new ChargeAction(
            $configurationProvider,
            $chargeRequestBuilder,
            $paymentApiFactory
        );

        $sut->handleFlow(
            $this->createStorableFlow($order)
        );
    }

    private function createOrderEntity(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order_uuid');
        $order->setSalesChannelId('test_sales_channel_id');
        $order->setTransactions(new OrderTransactionCollection([]));

        return $order;
    }

    private function createNotChargedRetrievePaymentResult(): RetrievePaymentResult
    {
        return RetrievePaymentResult::fromJson('{
            "payment": {
                "paymentId": "025400006091b1ef6937598058c4e487",
                "summary": {
                    "reservedAmount": 100
                },
                "consumer": {
                    "shippingAddress": {},
                    "billingAddress": {},
                    "privatePerson": {},
                    "company": {}
                },
                "paymentDetails": {},
                "orderDetails": {
                    "amount": 100,
                    "currency": "EUR"
                },
                "checkout": {
                    "url": "https://example.com/checkout",
                    "cancelUrl": null
                },
                "created": "2019-08-24T14:15:22Z",
                "refunds": [],
                "charges": []
            }
        }');
    }

    private function createAlreadyChargedRetrievePaymentResult(): RetrievePaymentResult
    {
        return RetrievePaymentResult::fromJson('{
            "payment": {
                "paymentId": "025400006091b1ef6937598058c4e487",
                "summary": {
                    "reservedAmount": 100,
                    "chargedAmount": 50
                },
                "consumer": {
                    "shippingAddress": {},
                    "billingAddress": {},
                    "privatePerson": {},
                    "company": {}
                },
                "paymentDetails": {},
                "orderDetails": {
                    "amount": 100,
                    "currency": "EUR"
                },
                "checkout": {
                    "url": "https://example.com/checkout",
                    "cancelUrl": null
                },
                "created": "2019-08-24T14:15:22Z",
                "refunds": [],
                "charges": [
                    {
                        "chargeId": "test_charge_id",
                        "amount": 50,
                        "created": "2019-08-24T14:15:22Z",
                        "orderItems": []
                    }
                ]
            }
        }');
    }

    private function createConfigurationProviderMock(): ConfigurationProvider|MockObject
    {
        return $this->createConfiguredMock(
            ConfigurationProvider::class,
            [
                'getSecretKey' => 'secret',
                'isLiveMode' => true,
            ],
        );
    }

    private function createPaymentApiFactoryMock(MockObject $paymentApi): PaymentApiFactory|MockObject
    {
        $paymentApiFactory = $this->createMock(PaymentApiFactory::class);
        $paymentApiFactory
            ->method('create')
            ->with('secret', true)
            ->willReturn($paymentApi);

        return $paymentApiFactory;
    }

    private function createStorableFlow(OrderEntity $order): StorableFlow
    {
        return new StorableFlow(
            'test',
            Context::createDefaultContext(),
            [],
            [
                OrderAware::ORDER => $order,
            ]
        );
    }
}
