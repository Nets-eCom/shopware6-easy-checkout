<?php

declare(strict_types=1);

namespace Nexi\Checkout\Lifecycle;

use Nexi\Checkout\Configuration\ConfigurationProvider;
use Nexi\Checkout\Handler\EmbeddedPayment;
use Nexi\Checkout\Handler\HostedPayment;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final readonly class PaymentMethodsActivator implements PaymentMethodActivatorInterface, PaymentMethodDeactivatorInterface
{
    /**
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        private EntityRepository $paymentMethodRepository,
        private SystemConfigService $systemConfigService
    ) {
    }

    public function activate(Context $context): void
    {
        if (!$this->areMethodsInstalled($context, [HostedPayment::class, EmbeddedPayment::class])) {
            return;
        }

        $this
            ->paymentMethodRepository->update(
                [
                    [
                        'id' => $this->getMethodIdSearchResult($context, [HostedPayment::class])->firstId(),
                        'active' => true,
                    ],
                    [
                        'id' => $this->getMethodIdSearchResult($context, [EmbeddedPayment::class])->firstId(),
                        'active' => true,
                    ],
                ],
                $context
            );

        $this->setRandomWebhookAuthorizationHeader();
    }

    public function deactivate(Context $context): void
    {
        if (!$this->areMethodsInstalled($context, [HostedPayment::class, EmbeddedPayment::class])) {
            return;
        }

        $this
            ->paymentMethodRepository->update(
                [
                    [
                        'id' => $this->getMethodIdSearchResult($context, [HostedPayment::class])->firstId(),
                        'active' => false,
                    ],
                    [
                        'id' => $this->getMethodIdSearchResult($context, [EmbeddedPayment::class])->firstId(),
                        'active' => false,
                    ],
                ],
                $context
            );
    }

    /**
     * @param string[] $handlerIdentifiers
     */
    private function areMethodsInstalled(Context $context, array $handlerIdentifiers): bool
    {
        return $this->getMethodIdSearchResult($context, $handlerIdentifiers)->getTotal() > 0;
    }

    /**
     * @param string[] $handlerIdentifiers
     */
    private function getMethodIdSearchResult(Context $context, array $handlerIdentifiers): IdSearchResult
    {
        return $this
            ->paymentMethodRepository
            ->searchIds(
                (new Criteria())
                    ->addFilter(
                        new EqualsAnyFilter('handlerIdentifier', $handlerIdentifiers),
                    ),
                $context
            );
    }

    private function setRandomWebhookAuthorizationHeader(): void
    {
        $webhookAuthorizationHeader = $this->systemConfigService->getString(
            ConfigurationProvider::WEBHOOK_AUTHORIZATION_HEADER
        );

        if ($webhookAuthorizationHeader !== '') {
            return;
        }

        $this->systemConfigService->set(
            ConfigurationProvider::WEBHOOK_AUTHORIZATION_HEADER,
            Random::getAlphanumericString(15)
        );
    }
}
