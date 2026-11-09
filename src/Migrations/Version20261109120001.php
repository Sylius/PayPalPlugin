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
use Sylius\Component\Payment\Encryption\EncryptionCheckTrait;
use Sylius\PayPalPlugin\Doctrine\Migrations\SandboxModeAwareMigrationInterface;

final class Version20261109120001 extends AbstractPostgreSQLMigration implements SandboxModeAwareMigrationInterface
{
    use EncryptionCheckTrait;

    private bool $sandbox = true;

    public function setSandbox(bool $sandbox): void
    {
        $this->sandbox = $sandbox;
    }

    public function getDescription(): string
    {
        return 'Store the PayPal sandbox mode in the gateway config';
    }

    public function up(Schema $schema): void
    {
        $gatewayConfigs = $this->connection->fetchAllAssociative(
            'SELECT id, gateway_name, config FROM sylius_gateway_config WHERE factory_name = ?',
            ['sylius_paypal'],
            [ParameterType::STRING],
        );

        foreach ($gatewayConfigs as $gatewayConfig) {
            $config = json_decode((string) $gatewayConfig['config'], true, 512, \JSON_THROW_ON_ERROR);
            $config = is_array($config) ? $config : [];
            if (array_key_exists('sandbox', $config)) {
                continue;
            }

            if ([] !== $config && $this->isEncrypted(current($config))) {
                $this->warnIf(true, sprintf(
                    'The PayPal gateway config "%s" is encrypted, so its mode could not be stored. Set its "sandbox" key or connect the PayPal account again through the onboarding.',
                    (string) $gatewayConfig['gateway_name'],
                ));

                continue;
            }

            $config['sandbox'] = $this->sandbox;

            $this->addSql(
                'UPDATE sylius_gateway_config SET config = ? WHERE id = ?',
                [json_encode($config, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR), $gatewayConfig['id']],
                [ParameterType::STRING, ParameterType::INTEGER],
            );
        }
    }

    public function down(Schema $schema): void
    {
    }
}
