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
use Doctrine\Migrations\Exception\IrreversibleMigration;
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

        $this->migrate();

        self::assertSame(
            [false, ['client_id' => 'CLIENT_ID', 'sandbox' => true, 'use_authorize' => false]],
            $this->storedGatewayConfig($gatewayConfigId),
        );
    }

    public function test_it_keeps_the_strategy_of_a_paypal_gateway_config_already_off_payum(): void
    {
        $gatewayConfigId = $this->loadPayPalGatewayConfigId();
        $this->storeGatewayConfig($gatewayConfigId, false, ['client_id' => 'CLIENT_ID', 'use_authorize' => true]);

        $this->migrate();

        self::assertSame([false, ['client_id' => 'CLIENT_ID', 'use_authorize' => true]], $this->storedGatewayConfig($gatewayConfigId));
    }

    public function test_it_leaves_gateway_configs_of_other_factories_on_payum(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);
        /** @var PaymentMethodInterface $cashOnDelivery */
        $cashOnDelivery = $fixtures['cash_on_delivery'];
        $gatewayConfigId = (int) $cashOnDelivery->getGatewayConfig()?->getId();
        $this->storeGatewayConfig($gatewayConfigId, true, ['use_authorize' => true]);

        $this->migrate();

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

    private function migrate(): void
    {
        $migration = $this->migration();
        $migration->up(new Schema());

        foreach ($migration->getSql() as $query) {
            $this->connection()->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
    }

    private function migration(): AbstractMigration
    {
        if ($this->connection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return new Version20261104120001($this->connection(), new NullLogger());
        }

        return new Version20261104120000($this->connection(), new NullLogger());
    }

    private function connection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }
}
