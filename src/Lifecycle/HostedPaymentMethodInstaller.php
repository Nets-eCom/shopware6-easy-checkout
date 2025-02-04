<?php

declare(strict_types=1);

namespace Nexi\Checkout\Lifecycle;

use Nexi\Checkout\Handler\HostedPayment;
use Nexi\Checkout\NexiCheckout;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class HostedPaymentMethodInstaller implements PaymentMethodInstallerInterface
{
    /**
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        private PluginIdProvider $pluginIdProvider,
        private EntityRepository $paymentMethodRepository
    ) {
    }

    public function install(Context $context): void
    {
        $methodData = [
            'id' => Uuid::randomHex(),
            'handlerIdentifier' => HostedPayment::class,
            'name' => 'Nexi Checkout Hosted',
            'description' => 'Nexi Checkout Hosted',
            'technicalName' => 'nexi_checkout_hosted',
            'pluginId' => $this->pluginIdProvider->getPluginIdByBaseClass(NexiCheckout::class, $context),
        ];

        $previousMethodId = $this->getMethodIdSearchResult($context, HostedPayment::class)->firstId();

        if ($previousMethodId !== null) {
            $methodData['id'] = $previousMethodId;
        }

        $this->paymentMethodRepository->upsert([$methodData], $context);
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
