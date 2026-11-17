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

namespace Tests\Sylius\PayPalPlugin\DependencyInjection\Compiler;

use Doctrine\Migrations\Version\MigrationFactory;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Sylius\PayPalPlugin\DependencyInjection\Compiler\SandboxModeAwareMigrationFactoryPass;
use Sylius\PayPalPlugin\Doctrine\Migrations\SandboxModeAwareMigrationFactory;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class SandboxModeAwareMigrationFactoryPassTest extends AbstractCompilerPassTestCase
{
    public function test_it_decorates_the_migration_factory_the_migrations_are_created_with(): void
    {
        $this->setParameter('.sylius_paypal.sandbox', false);
        $this->setDefinition('app.migration_factory', new Definition(MigrationFactory::class));
        $this->setDefinition('doctrine.migrations.dependency_factory', (new Definition())->addMethodCall(
            'setDefinition',
            [MigrationFactory::class, new ServiceClosureArgument(new Reference('app.migration_factory'))],
        ));

        $this->compile();

        $this->assertContainerBuilderHasService('sylius_paypal.doctrine.migrations.sandbox_mode_aware_factory', SandboxModeAwareMigrationFactory::class);
        self::assertSame(
            ['app.migration_factory', null, 0],
            $this->container->getDefinition('sylius_paypal.doctrine.migrations.sandbox_mode_aware_factory')->getDecoratedService(),
        );
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('sylius_paypal.doctrine.migrations.sandbox_mode_aware_factory', 1, '%.sylius_paypal.sandbox%');
    }

    public function test_it_does_nothing_without_the_doctrine_migrations(): void
    {
        $this->setParameter('.sylius_paypal.sandbox', true);

        $this->compile();

        $this->assertContainerBuilderNotHasService('sylius_paypal.doctrine.migrations.sandbox_mode_aware_factory');
    }

    public function test_it_does_nothing_when_no_migration_factory_is_configured(): void
    {
        $this->setParameter('.sylius_paypal.sandbox', true);
        $this->setDefinition('doctrine.migrations.dependency_factory', new Definition());

        $this->compile();

        $this->assertContainerBuilderNotHasService('sylius_paypal.doctrine.migrations.sandbox_mode_aware_factory');
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new SandboxModeAwareMigrationFactoryPass());
    }
}
