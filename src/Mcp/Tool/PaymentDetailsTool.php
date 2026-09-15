<?php

declare(strict_types=1);

namespace Nexi\Checkout\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Nexi\Checkout\Mcp\NexiTransactionResolver;
use Nexi\Checkout\Mcp\PaymentDetailsService;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(
    name: 'nexi-checkout-payment-details',
    description: 'Fetch fresh Nexi Checkout payment details. Provide exactly one Shopware orderId, orderNumber, or Nexi paymentId.',
    annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true),
)]
#[McpToolRequires('order:read')]
final class PaymentDetailsTool extends McpToolResponse
{
    public function __construct(
        private readonly McpContextProvider $contextProvider,
        private readonly NexiTransactionResolver $transactionResolver,
        private readonly PaymentDetailsService $paymentDetails,
    ) {
    }

    public function __invoke(string $orderId = '', string $orderNumber = '', string $paymentId = ''): string
    {
        $context = $this->contextProvider->getContext();
        $error = $this->requirePrivilege($context, 'order:read');
        if ($error !== null) {
            return $error;
        }

        try {
            $transaction = $this->transactionResolver->resolve($orderId, $orderNumber, $paymentId);

            return $this->success($this->paymentDetails->getDetails($transaction));
        } catch (\InvalidArgumentException $invalidArgumentException) {
            return $this->error($invalidArgumentException->getMessage());
        }
    }
}
