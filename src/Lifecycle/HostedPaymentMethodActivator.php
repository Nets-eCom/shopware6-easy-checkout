<?php

declare(strict_types=1);

namespace NexiNets\Lifecycle;

use NexiNets\Handler\HostedPayment;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

final readonly class HostedPaymentMethodActivator implements PaymentMethodActivatorInterface, PaymentMethodDeactivatorInterface
{
    /**
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        private EntityRepository $paymentMethodRepository
    ) {
    }

    public function activate(Context $context): void
    {
        if (!$this->isMethodInstalled($context, HostedPayment::class)) {
            return;
        }

        $this
            ->paymentMethodRepository->update(
                [
                    [
                        'id' => $this->getMethodIdSearchResult($context, HostedPayment::class)->firstId(),
                        'active' => true,
                    ],
                ],
                $context
            );
    }

    public function deactivate(Context $context): void
    {
        if (!$this->isMethodInstalled($context, HostedPayment::class)) {
            return;
        }

        $this
            ->paymentMethodRepository->update(
                [
                    [
                        'id' => $this->getMethodIdSearchResult($context, HostedPayment::class)->firstId(),
                        'active' => false,
                    ],
                ],
                $context
            );
    }

    private function isMethodInstalled(Context $context, string $handlerIdentifier): bool
    {
        return $this->getMethodIdSearchResult($context, $handlerIdentifier)->getTotal() > 0;
    }

    private function getMethodIdSearchResult(Context $context, string $handlerIdentifier): IdSearchResult
    {
        return $this
            ->paymentMethodRepository
            ->searchIds(
                (new Criteria())
                    ->addFilter(
                        new EqualsFilter('handlerIdentifier', $handlerIdentifier)
                    ),
                $context
            );
    }
}
