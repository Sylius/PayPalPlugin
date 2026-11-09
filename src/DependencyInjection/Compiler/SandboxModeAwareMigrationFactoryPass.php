<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\DependencyInjection\Compiler;

use Doctrine\Migrations\Version\MigrationFactory;
use Sylius\PayPalPlugin\Doctrine\Migrations\SandboxModeAwareMigrationFactory;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class SandboxModeAwareMigrationFactoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (
            !$container->hasDefinition('doctrine.migrations.dependency_factory') ||
            !$container->hasParameter('.sylius_paypal.sandbox')
        ) {
            return;
        }

        $migrationFactoryId = $this->findMigrationFactoryId($container->getDefinition('doctrine.migrations.dependency_factory'));
        if (null === $migrationFactoryId) {
            return;
        }

        $container
            ->register('sylius_paypal.doctrine.migrations.sandbox_mode_aware_factory', SandboxModeAwareMigrationFactory::class)
            ->setDecoratedService($migrationFactoryId)
            ->setArguments([new Reference('.inner'), '%.sylius_paypal.sandbox%'])
        ;
    }

    private function findMigrationFactoryId(Definition $dependencyFactory): ?string
    {
        foreach ($dependencyFactory->getMethodCalls() as [$method, $arguments]) {
            if ('setDefinition' !== $method || MigrationFactory::class !== ($arguments[0] ?? null)) {
                continue;
            }

            $migrationFactory = $arguments[1] ?? null;
            if ($migrationFactory instanceof ServiceClosureArgument) {
                $migrationFactory = $migrationFactory->getValues()[0] ?? null;
            }

            return $migrationFactory instanceof Reference ? (string) $migrationFactory : null;
        }

        return null;
    }
}
