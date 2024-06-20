<?php

declare(strict_types=1);

namespace NexiNets;

use NexiNets\Lifecycle\HostedPaymentMethodActivator;
use NexiNets\Lifecycle\HostedPaymentMethodInstaller;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class NetsCheckout extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        $this
            ->getPaymentMethodInstalled()
            ->install($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        // Remove or deactivate the data created by the plugin
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->getPaymentMethodActivator()->activate($activateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        // Deactivate entities, such as a new payment method
        // Or remove previously created entities
    }

    public function update(UpdateContext $updateContext): void
    {
        // Update necessary stuff, mostly non-database related
    }

    public function postInstall(InstallContext $installContext): void
    {
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
    }

    private function getPaymentMethodInstalled(): HostedPaymentMethodInstaller
    {
        return new HostedPaymentMethodInstaller(
            $this->container->get(PluginIdProvider::class),
            $this->container->get(sprintf('%s.repository', PaymentMethodDefinition::ENTITY_NAME))
        );
    }

    private function getPaymentMethodActivator(): HostedPaymentMethodActivator
    {
        return new HostedPaymentMethodActivator(
            $this->container->get(sprintf('%s.repository', PaymentMethodDefinition::ENTITY_NAME))
        );
    }
}
