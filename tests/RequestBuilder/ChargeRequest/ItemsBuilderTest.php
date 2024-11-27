<?php declare(strict_types=1);

namespace NexiNets\Tests\RequestBuilder\ChargeRequest;

use NexiNets\Administration\Model\ChargeData;
use NexiNets\CheckoutApi\Model\Request\Item;
use NexiNets\Dictionary\OrderTransactionDictionary;
use NexiNets\RequestBuilder\ChargeRequest\ItemsBuilder;
use NexiNets\RequestBuilder\Helper\FormatHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureCollection;
use Shopware\Core\Checkout\Order\OrderEntity;

final class ItemsBuilderTest extends TestCase
{
    public function testCreateForCharge(): void
    {
        $object = new ItemsBuilder(new FormatHelper());

        $chargeData = new ChargeData(1300, [
            [
                'reference' => 'product-1',
                'quantity' => 1,
                'amount' => 10.00,
            ],
            [
                'reference' => 'product-2',
                'quantity' => 3,
                'amount' => 3.00,
            ],
        ]);

        $orderSendOnPaymentCreate = [
            'items' => [
                [
                    'name' => 'product-1 name',
                    'quantity' => 2,
                    'unit' => 'pcs',
                    'unitPrice' => 900,
                    'grossTotalAmount' => 2000,
                    'netTotalAmount' => 1800,
                    'reference' => 'product-1',
                    'taxRate' => 1000,
                    'taxAmount' => 200,
                ],
                [
                    'name' => 'product-2 name',
                    'quantity' => 3,
                    'unit' => 'pcs',
                    'unitPrice' => 100,
                    'grossTotalAmount' => 300,
                    'netTotalAmount' => 300,
                    'reference' => 'product-2',
                ],
            ],
            'currency' => 'EUR',
            'amount' => 5000,
            'reference' => null,
        ];

        $items = $object->createForCharge($chargeData, $orderSendOnPaymentCreate);

        $this->assertCount(2, $items);
        $this->assertContainsOnlyInstancesOf(Item::class, $items);

        $item0Array = $items[0]->jsonSerialize();
        $this->assertEquals(1, $item0Array['quantity']);
        $this->assertEquals(900, $item0Array['unitPrice']);
        $this->assertEquals(1000, $item0Array['grossTotalAmount']);
        $this->assertEquals(900, $item0Array['netTotalAmount']);
        $this->assertEquals(100, $item0Array['taxAmount']);
        $this->assertEquals(1000, $item0Array['taxRate']);

        $item1Array = $items[1]->jsonSerialize();
        $this->assertEquals(3, $item1Array['quantity']);
        $this->assertEquals(100, $item1Array['unitPrice']);
        $this->assertEquals(300, $item1Array['grossTotalAmount']);
        $this->assertEquals(300, $item1Array['netTotalAmount']);
        $this->assertArrayNotHasKey('taxAmount', $item1Array);
        $this->assertArrayNotHasKey('taxRate', $item1Array);
    }

    public function testCreateUnrelatedPartialChargeItem(): void
    {
        $object = new ItemsBuilder(new FormatHelper());

        $items = $object->createUnrelatedPartialChargeItem(
            $this->createOrderTransactionEntity(),
            10.00
        );

        $this->assertCount(1, $items);
        $this->assertContainsOnlyInstancesOf(Item::class, $items);

        $item0Array = $items[0]->jsonSerialize();
        $this->assertEquals(1, $item0Array['quantity']);
        $this->assertEquals(1000, $item0Array['unitPrice']);
        $this->assertEquals(1000, $item0Array['grossTotalAmount']);
        $this->assertEquals(1000, $item0Array['netTotalAmount']);
    }

    private function createOrderTransactionEntity(): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction_uuid');
        $transaction->setCustomFields([
            OrderTransactionDictionary::CUSTOM_FIELDS_NEXI_NETS_PAYMENT_ID => '025400006091b1ef6937598058c4e487',
        ]);
        $transaction->setCaptures(new OrderTransactionCaptureCollection([]));

        $order = new OrderEntity();
        $order->setId('order_uuid');
        $order->setOrderNumber('order_number');

        $transaction->setOrder($order);

        return $transaction;
    }
}
