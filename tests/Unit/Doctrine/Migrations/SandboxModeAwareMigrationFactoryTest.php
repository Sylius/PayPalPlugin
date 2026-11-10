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

namespace Tests\Sylius\PayPalPlugin\Unit\Doctrine\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\PayPalPlugin\Doctrine\Migrations\SandboxModeAwareMigrationFactory;
use Sylius\PayPalPlugin\Doctrine\Migrations\SandboxModeAwareMigrationInterface;

final class SandboxModeAwareMigrationFactoryTest extends TestCase
{
    public function test_it_passes_the_sandbox_mode_to_a_sandbox_mode_aware_migration(): void
    {
        $migration = new class($this->createMock(Connection::class), new NullLogger()) extends AbstractMigration implements SandboxModeAwareMigrationInterface {
            public ?bool $sandbox = null;

            public function setSandbox(bool $sandbox): void
            {
                $this->sandbox = $sandbox;
            }

            public function up(Schema $schema): void
            {
            }
        };

        $migrationFactory = $this->createMock(MigrationFactory::class);
        $migrationFactory->method('createVersion')->with('Version1')->willReturn($migration);

        self::assertSame($migration, (new SandboxModeAwareMigrationFactory($migrationFactory, false))->createVersion('Version1'));
        self::assertFalse($migration->sandbox);
    }

    public function test_it_returns_any_other_migration_as_it_is(): void
    {
        $migration = $this->getMockBuilder(AbstractMigration::class)->disableOriginalConstructor()->getMock();

        $migrationFactory = $this->createMock(MigrationFactory::class);
        $migrationFactory->method('createVersion')->with('Version1')->willReturn($migration);

        self::assertSame($migration, (new SandboxModeAwareMigrationFactory($migrationFactory, true))->createVersion('Version1'));
    }
}
