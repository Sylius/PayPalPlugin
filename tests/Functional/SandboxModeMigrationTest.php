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

namespace Tests\Sylius\PayPalPlugin\Functional;

use ApiTestCase\JsonApiTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\DependencyFactory;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Migrations\Version20261109120000;
use Sylius\PayPalPlugin\Migrations\Version20261109120001;

final class SandboxModeMigrationTest extends JsonApiTestCase
{
    public function test_it_stores_the_sandbox_mode_of_2_x_in_a_paypal_gateway_config_without_one(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $this->storeGatewayConfig($gatewayConfigId, ['client_id' => 'CLIENT_ID']);

        $this->migrate($this->migrationFromTheApplication());

        self::assertSame(['client_id' => 'CLIENT_ID', 'sandbox' => true], $this->storedGatewayConfig($gatewayConfigId));
    }

    public function test_it_stores_the_production_mode_resolved_from_the_configuration(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $this->storeGatewayConfig($gatewayConfigId, ['client_id' => 'CLIENT_ID']);

        $migration = $this->migration();
        $migration->setSandbox(false);
        $this->migrate($migration);

        self::assertSame(['client_id' => 'CLIENT_ID', 'sandbox' => false], $this->storedGatewayConfig($gatewayConfigId));
    }

    public function test_it_keeps_the_mode_a_paypal_gateway_config_already_has(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $this->storeGatewayConfig($gatewayConfigId, ['client_id' => 'CLIENT_ID', 'sandbox' => false]);

        $this->migrate($this->migration());

        self::assertSame(['client_id' => 'CLIENT_ID', 'sandbox' => false], $this->storedGatewayConfig($gatewayConfigId));
    }

    public function test_it_stores_the_mode_next_to_the_values_payum_encrypted(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $this->storeGatewayConfig($gatewayConfigId, ['client_id' => 'PAYUM_ENCRYPTED', 'encrypted' => true]);

        $this->migrate($this->migration());

        self::assertSame(
            ['client_id' => 'PAYUM_ENCRYPTED', 'encrypted' => true, 'sandbox' => true],
            $this->storedGatewayConfig($gatewayConfigId),
        );
    }

    public function test_it_reports_a_paypal_gateway_config_sylius_already_encrypted_and_leaves_it_as_it_is(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $config = ['client_id' => 'CLIENT_ID#ENCRYPTED', 'client_secret' => 'CLIENT_SECRET#ENCRYPTED'];
        $this->storeGatewayConfig($gatewayConfigId, $config);
        $logger = new class() extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };

        $this->migrate($this->migration($logger));

        self::assertSame($config, $this->storedGatewayConfig($gatewayConfigId));
        self::assertCount(1, $logger->messages);
        self::assertStringContainsString('The PayPal gateway config "paypal" is encrypted', $logger->messages[0]);
    }

    public function test_it_leaves_gateway_configs_of_other_factories_alone(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);
        /** @var PaymentMethodInterface $cashOnDelivery */
        $cashOnDelivery = $fixtures['cash_on_delivery'];
        $gatewayConfigId = (int) $cashOnDelivery->getGatewayConfig()?->getId();
        $this->storeGatewayConfig($gatewayConfigId, ['use_authorize' => true]);

        $this->migrate($this->migration());

        self::assertSame(['use_authorize' => true], $this->storedGatewayConfig($gatewayConfigId));
    }

    private function loadPayPalGatewayConfigId(): int
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml'])['paypal'];

        return (int) $paymentMethod->getGatewayConfig()?->getId();
    }

    /** @param array<string, mixed> $config */
    private function storeGatewayConfig(int $id, array $config): void
    {
        $this->connection()->executeStatement(
            'UPDATE sylius_gateway_config SET config = ? WHERE id = ?',
            [json_encode($config, \JSON_THROW_ON_ERROR), $id],
            [ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    /** @return array<string, mixed> */
    private function storedGatewayConfig(int $id): array
    {
        /** @var string $storedConfig */
        $storedConfig = $this->connection()->fetchOne('SELECT config FROM sylius_gateway_config WHERE id = ?', [$id]);

        /** @var array<string, mixed> $config */
        $config = json_decode($storedConfig, true, 512, \JSON_THROW_ON_ERROR);
        ksort($config);

        return $config;
    }

    private function migrate(AbstractMigration $migration): void
    {
        $migration->up(new Schema());

        foreach ($migration->getSql() as $query) {
            $this->connection()->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
    }

    private function migrationFromTheApplication(): AbstractMigration
    {
        /** @var DependencyFactory $dependencyFactory */
        $dependencyFactory = self::getContainer()->get('doctrine.migrations.dependency_factory');

        return $dependencyFactory->getMigrationFactory()->createVersion($this->migrationClass());
    }

    private function migration(?AbstractLogger $logger = null): Version20261109120000|Version20261109120001
    {
        $migrationClass = $this->migrationClass();

        return new $migrationClass($this->connection(), $logger ?? new NullLogger());
    }

    /** @return class-string<Version20261109120000|Version20261109120001> */
    private function migrationClass(): string
    {
        if ($this->connection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return Version20261109120001::class;
        }

        return Version20261109120000::class;
    }

    private function connection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }
}
