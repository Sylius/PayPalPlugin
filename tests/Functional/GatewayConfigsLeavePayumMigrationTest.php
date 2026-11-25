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
use Doctrine\Migrations\Exception\AbortMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Migrations\Version20261104120000;
use Sylius\PayPalPlugin\Migrations\Version20261104120001;

final class GatewayConfigsLeavePayumMigrationTest extends JsonApiTestCase
{
    public function test_it_moves_a_paypal_gateway_config_off_payum_and_keeps_it_capturing(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $this->storeGatewayConfig($gatewayConfigId, true, ['client_id' => 'CLIENT_ID', 'sandbox' => true, 'use_authorize' => true]);

        $this->migrate($this->migration());

        self::assertSame(
            [false, ['client_id' => 'CLIENT_ID', 'sandbox' => true, 'use_authorize' => false]],
            $this->storedGatewayConfig($gatewayConfigId),
        );
    }

    public function test_it_drops_the_sftp_credentials_of_a_paypal_gateway_config_leaving_payum(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $this->storeGatewayConfig($gatewayConfigId, true, [
            'client_id' => 'CLIENT_ID',
            'reports_sftp_password' => 'PASSWORD',
            'reports_sftp_username' => 'USERNAME',
            'sandbox' => true,
            'use_authorize' => true,
        ]);

        $this->migrate($this->migration());

        self::assertSame(
            [false, ['client_id' => 'CLIENT_ID', 'sandbox' => true, 'use_authorize' => false]],
            $this->storedGatewayConfig($gatewayConfigId),
        );
    }

    public function test_it_stores_the_sandbox_mode_of_2_x_with_the_rest_of_the_changes_in_one_update(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $this->storeGatewayConfig($gatewayConfigId, true, [
            'client_id' => 'CLIENT_ID',
            'reports_sftp_password' => 'PASSWORD',
            'reports_sftp_username' => 'USERNAME',
            'use_authorize' => true,
        ]);

        $migration = $this->migrationFromTheApplication();
        $migration->up(new Schema());

        self::assertCount(1, $migration->getSql());
        $this->execute($migration);
        self::assertSame(
            [false, ['client_id' => 'CLIENT_ID', 'sandbox' => true, 'use_authorize' => false]],
            $this->storedGatewayConfig($gatewayConfigId),
        );
    }

    public function test_it_stores_the_production_mode_resolved_from_the_configuration(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $this->storeGatewayConfig($gatewayConfigId, true, ['client_id' => 'CLIENT_ID', 'use_authorize' => true]);

        $this->migrate($this->migration(false));

        self::assertSame(
            [false, ['client_id' => 'CLIENT_ID', 'sandbox' => false, 'use_authorize' => false]],
            $this->storedGatewayConfig($gatewayConfigId),
        );
    }

    public function test_it_stores_only_the_sandbox_mode_of_a_paypal_gateway_config_already_off_payum(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $this->storeGatewayConfig($gatewayConfigId, false, [
            'client_id' => 'CLIENT_ID',
            'reports_sftp_password' => 'PASSWORD',
            'reports_sftp_username' => 'USERNAME',
            'use_authorize' => true,
        ]);

        $this->migrate($this->migration());

        self::assertSame(
            [false, ['client_id' => 'CLIENT_ID', 'reports_sftp_password' => 'PASSWORD', 'reports_sftp_username' => 'USERNAME', 'sandbox' => true, 'use_authorize' => true]],
            $this->storedGatewayConfig($gatewayConfigId),
        );
    }

    public function test_it_leaves_a_paypal_gateway_config_already_off_payum_with_a_mode_untouched(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $this->storeGatewayConfig($gatewayConfigId, false, ['client_id' => 'CLIENT_ID', 'sandbox' => false, 'use_authorize' => true]);

        $migration = $this->migration();
        $migration->up(new Schema());

        self::assertSame([], $migration->getSql());
    }

    public function test_it_stores_the_sandbox_mode_next_to_the_values_payum_encrypted(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $this->storeGatewayConfig($gatewayConfigId, true, ['client_id' => 'PAYUM_ENCRYPTED', 'encrypted' => true, 'use_authorize' => true]);

        $this->migrate($this->migration());

        self::assertSame(
            [false, ['client_id' => 'PAYUM_ENCRYPTED', 'encrypted' => true, 'sandbox' => true, 'use_authorize' => false]],
            $this->storedGatewayConfig($gatewayConfigId),
        );
    }

    public function test_it_reports_a_paypal_gateway_config_sylius_already_encrypted_and_leaves_it_as_it_is(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $config = ['client_id' => 'CLIENT_ID#ENCRYPTED', 'client_secret' => 'CLIENT_SECRET#ENCRYPTED'];
        $this->storeGatewayConfig($gatewayConfigId, false, $config);
        $logger = new class() extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };

        $this->migrate($this->migration(true, $logger));

        self::assertSame([false, $config], $this->storedGatewayConfig($gatewayConfigId));
        self::assertCount(1, $logger->messages);
        self::assertStringContainsString('The PayPal gateway config "paypal" is encrypted, so its mode could not be stored and it is used in production.', $logger->messages[0]);
    }

    public function test_it_stops_when_the_sandbox_mode_was_not_passed_in(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $this->storeGatewayConfig($gatewayConfigId, true, ['client_id' => 'CLIENT_ID', 'use_authorize' => true]);

        $this->expectException(AbortMigration::class);

        $this->migration(null)->up(new Schema());
    }

    public function test_it_does_not_need_the_sandbox_mode_when_every_paypal_gateway_config_has_one(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $this->storeGatewayConfig($gatewayConfigId, true, ['client_id' => 'CLIENT_ID', 'sandbox' => true, 'use_authorize' => true]);

        $this->migrate($this->migration(null));

        self::assertSame(
            [false, ['client_id' => 'CLIENT_ID', 'sandbox' => true, 'use_authorize' => false]],
            $this->storedGatewayConfig($gatewayConfigId),
        );
    }

    public function test_it_leaves_gateway_configs_of_other_factories_on_payum(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);
        /** @var PaymentMethodInterface $cashOnDelivery */
        $cashOnDelivery = $fixtures['cash_on_delivery'];
        $gatewayConfigId = (int) $cashOnDelivery->getGatewayConfig()?->getId();
        $this->storeGatewayConfig($gatewayConfigId, true, ['use_authorize' => true]);

        $this->migrate($this->migration());

        self::assertSame([true, ['use_authorize' => true]], $this->storedGatewayConfig($gatewayConfigId));
    }

    public function test_it_cannot_be_reverted(): void
    {
        $this->expectException(IrreversibleMigration::class);

        $this->migration()->down(new Schema());
    }

    private function loadPayPalGatewayConfigId(): int
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml'])['paypal'];

        return (int) $paymentMethod->getGatewayConfig()?->getId();
    }

    /** @param array<string, mixed> $config */
    private function storeGatewayConfig(int $id, bool $usePayum, array $config): void
    {
        $this->connection()->executeStatement(
            'UPDATE sylius_gateway_config SET use_payum = ?, config = ? WHERE id = ?',
            [$usePayum, json_encode($config, \JSON_THROW_ON_ERROR), $id],
            [ParameterType::BOOLEAN, ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    /** @return array{bool, array<string, mixed>} */
    private function storedGatewayConfig(int $id): array
    {
        /** @var array{use_payum: int|bool|string, config: string} $row */
        $row = $this->connection()->fetchAssociative('SELECT use_payum, config FROM sylius_gateway_config WHERE id = ?', [$id]);

        /** @var array<string, mixed> $config */
        $config = json_decode($row['config'], true, 512, \JSON_THROW_ON_ERROR);
        ksort($config);

        return [(bool) $row['use_payum'], $config];
    }

    private function migrate(AbstractMigration $migration): void
    {
        $migration->up(new Schema());
        $this->execute($migration);
    }

    private function execute(AbstractMigration $migration): void
    {
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

    private function migration(?bool $sandbox = true, ?AbstractLogger $logger = null): Version20261104120000|Version20261104120001
    {
        $migrationClass = $this->migrationClass();
        $migration = new $migrationClass($this->connection(), $logger ?? new NullLogger());
        if (null !== $sandbox) {
            $migration->setSandbox($sandbox);
        }

        return $migration;
    }

    /** @return class-string<Version20261104120000|Version20261104120001> */
    private function migrationClass(): string
    {
        if ($this->connection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return Version20261104120001::class;
        }

        return Version20261104120000::class;
    }

    private function connection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }
}
