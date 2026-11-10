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

final class Version20261104120001 extends AbstractPostgreSQLMigration implements SandboxModeAwareMigrationInterface
{
    use EncryptionCheckTrait;

    private ?bool $sandbox = null;

    public function setSandbox(bool $sandbox): void
    {
        $this->sandbox = $sandbox;
    }

    public function getDescription(): string
    {
        return 'Move PayPal gateway configs off Payum, keep them capturing, drop their SFTP credentials and store their sandbox mode';
    }

    public function up(Schema $schema): void
    {
        $gatewayConfigs = $this->connection->fetchAllAssociative(
            'SELECT id, gateway_name, use_payum, config FROM sylius_gateway_config WHERE factory_name = ?',
            ['sylius_paypal'],
            [ParameterType::STRING],
        );

        foreach ($gatewayConfigs as $gatewayConfig) {
            $config = json_decode((string) $gatewayConfig['config'], true, 512, \JSON_THROW_ON_ERROR);
            $config = is_array($config) ? $config : [];
            $changed = false;

            if ((bool) $gatewayConfig['use_payum']) {
                $config['use_authorize'] = false;
                unset($config['reports_sftp_username'], $config['reports_sftp_password']);
                $changed = true;
            }

            if (!array_key_exists('sandbox', $config)) {
                if ([] !== $config && $this->isEncrypted(current($config))) {
                    $this->warnIf(true, sprintf(
                        'The PayPal gateway config "%s" is encrypted, so its mode could not be stored. Set its "sandbox" key or connect the PayPal account again through the onboarding.',
                        (string) $gatewayConfig['gateway_name'],
                    ));
                } else {
                    $this->abortIf(
                        null === $this->sandbox,
                        'The PayPal sandbox mode was not passed to this migration. Run it through the console of an application with the PayPal plugin registered, so the "sylius_paypal.sandbox" option or the SYLIUS_PAYPAL_SANDBOX_ENABLED environment variable can be read.',
                    );
                    $config['sandbox'] = $this->sandbox;
                    $changed = true;
                }
            }

            if (!$changed) {
                continue;
            }

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
