<?php

declare(strict_types=1);

namespace Nexi\Checkout\DependencyInjection;

use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @TODO remove after Shopware upstream fix
 */
final class McpToolRegistrationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mcp.server.builder')) {
            return;
        }

        $allowedTools = $container->hasParameter('shopware.mcp.allowed_tools')
            ? $container->getParameter('shopware.mcp.allowed_tools')
            : [];

        if (!\is_array($allowedTools)) {
            throw new \InvalidArgumentException('The "shopware.mcp.allowed_tools" parameter must be an array.');
        }

        foreach (array_keys($container->findTaggedServiceIds('shopware.mcp.tool')) as $id) {
            $definition = $container->getDefinition($id);
            $class = $definition->getClass() ?? $id;

            if (
                $definition->isAbstract()
                || !str_starts_with($class, 'Nexi\\Checkout\\Mcp\\Tool\\')
                || !class_exists($class)
            ) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }

            // Excluded tools must not leave dangling references in the SDK locator
            // when Shopware subsequently removes their service definitions.
            if ($allowedTools !== []) {
                $attributes = $reflection->getAttributes(McpTool::class);
                $tool = isset($attributes[0]) ? $attributes[0]->newInstance() : null;
                if ($tool === null || !\in_array($tool->name, $allowedTools, true)) {
                    continue;
                }
            }

            // Shopware 6.7 can remap plugin tags after Symfony builds its MCP locator.
            // Remap our tools early while keeping the documented tag in plugin XML.
            if (!$definition->hasTag('mcp.tool')) {
                $definition->addTag('mcp.tool');
            }
        }
    }
}
