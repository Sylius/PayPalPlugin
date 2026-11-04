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

namespace Sylius\PayPalPlugin\Migrations;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractPostgreSQLMigration;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;

final class Version20261104120001 extends AbstractPostgreSQLMigration
{
    public function getDescription(): string
    {
        return 'Move PayPal gateway configs off Payum and keep them capturing';
    }

    public function up(Schema $schema): void
    {
        $gatewayConfigs = $this->connection->fetchAllAssociative(
            'SELECT id, config FROM sylius_gateway_config WHERE factory_name = ? AND use_payum = ?',
            [SyliusPayPalExtension::PAYPAL_FACTORY_NAME, true],
            [ParameterType::STRING, ParameterType::BOOLEAN],
        );

        foreach ($gatewayConfigs as $gatewayConfig) {
            $config = json_decode((string) $gatewayConfig['config'], true, 512, \JSON_THROW_ON_ERROR);
            $config = is_array($config) ? $config : [];
            $config[PayPalGatewayConfig::USE_AUTHORIZE] = false;

            $this->addSql(
                'UPDATE sylius_gateway_config SET use_payum = ?, config = ? WHERE id = ?',
                [false, json_encode($config, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR), $gatewayConfig['id']],
                [ParameterType::BOOLEAN, ParameterType::STRING, ParameterType::INTEGER],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('PayPal gateway configs cannot go back to Payum, which the plugin no longer ships.');
    }
}
