<?php

declare(strict_types=1);

namespace NexiNets;

use Doctrine\DBAL\Connection;
use NexiNets\Lifecycle\HostedPaymentMethodActivator;
use NexiNets\Lifecycle\HostedPaymentMethodInstaller;
use NexiNets\Lifecycle\UserDataRemover;
use NexiNets\Lifecycle\UserDataRemoverInterface;
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
        parent::install($installContext);

        $this
            ->getPaymentMethodInstalled()
            ->install($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        $this->getPaymentMethodActivator()->deactivate($uninstallContext->getContext());

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->getUserDataRemover()->removeUserData($this->container->get(Connection::class));
    }

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);
        $this->getPaymentMethodActivator()->activate($activateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        parent::deactivate($deactivateContext);
        $this->getPaymentMethodActivator()->deactivate($deactivateContext->getContext());
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

    private function getUserDataRemover(): UserDataRemoverInterface
    {
        return new UserDataRemover();
    }
}
