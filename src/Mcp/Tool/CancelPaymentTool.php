<?php

declare(strict_types=1);

namespace Nexi\Checkout\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Nexi\Checkout\Mcp\NexiTransactionResolver;
use Nexi\Checkout\Mcp\PaymentActionService;
use Nexi\Checkout\Order\Exception\OrderCancelException;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(
    name: 'nexi-checkout-cancel-payment',
    description: 'Preview or cancel a reserved Nexi Checkout payment. Provide exactly one orderId, orderNumber, or paymentId. Execution requires the exact confirmation returned by a preview.',
    annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true),
)]
#[McpToolRequires('order:read')]
#[McpToolRequires('order:update')]
final class CancelPaymentTool extends McpToolResponse
{
    public function __construct(
        private readonly McpContextProvider $contextProvider,
        private readonly NexiTransactionResolver $transactionResolver,
        private readonly PaymentActionService $paymentActions,
    ) {
    }

    public function __invoke(
        string $orderId = '',
        string $orderNumber = '',
        string $paymentId = '',
        string $confirmation = '',
    ): string {
        $context = $this->contextProvider->getContext();
        $error = $this->requirePrivilege($context, 'order:read', 'order:update');
        if ($error !== null) {
            return $error;
        }

        try {
            $transaction = $this->transactionResolver->resolve($orderId, $orderNumber, $paymentId);
            $result = $this->paymentActions->cancel($transaction, $confirmation);
        } catch (OrderCancelException $exception) {
            return $this->error('Nexi rejected the cancellation request.');
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage());
        }

        return $this->success($result);
    }
}
