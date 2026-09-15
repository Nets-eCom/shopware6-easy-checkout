<?php

declare(strict_types=1);

namespace Nexi\Checkout\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Nexi\Checkout\Mcp\NexiTransactionResolver;
use Nexi\Checkout\Mcp\PaymentActionService;
use Nexi\Checkout\Order\Exception\OrderRefundException;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(
    name: 'nexi-checkout-refund-payment',
    description: 'Preview or refund a Nexi Checkout payment. Provide exactly one orderId, orderNumber, or paymentId. Omit amount to refund all remaining charged funds. Execution requires the exact confirmation returned by a preview.',
    annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true),
)]
#[McpToolRequires('order:read')]
#[McpToolRequires('order:update')]
final class RefundPaymentTool extends McpToolResponse
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
        string $amount = '',
        string $confirmation = '',
    ): string {
        $context = $this->contextProvider->getContext();
        $error = $this->requirePrivilege($context, 'order:read', 'order:update');
        if ($error !== null) {
            return $error;
        }

        try {
            $transaction = $this->transactionResolver->resolve($orderId, $orderNumber, $paymentId);
            $result = $this->paymentActions->refund($transaction, $context, $amount, $confirmation);
        } catch (OrderRefundException $exception) {
            return $this->error('Nexi rejected the refund request. Fetch fresh payment details before retrying.');
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage());
        }

        return $this->success($result);
    }
}
