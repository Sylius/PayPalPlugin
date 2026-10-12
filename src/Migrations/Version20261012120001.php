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

use Doctrine\DBAL\Schema\Schema;
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractPostgreSQLMigration;

final class Version20261012120001 extends AbstractPostgreSQLMigration
{
    public function getDescription(): string
    {
        return 'Store the PayPal sandbox mode in the gateway config';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE sylius_gateway_config SET config = CAST(jsonb_set(CAST(config AS jsonb), \'{sandbox}\', CAST(:sandbox AS jsonb)) AS json) WHERE factory_name = :factoryName AND config->\'sandbox\' IS NULL',
            ['sandbox' => $this->isSandbox() ? 'true' : 'false', 'factoryName' => 'sylius_paypal'],
        );
    }

    public function down(Schema $schema): void
    {
    }

    private function isSandbox(): bool
    {
        $sandbox = $_ENV['SYLIUS_PAYPAL_SANDBOX_ENABLED'] ?? null;
        if (null === $sandbox) {
            return true;
        }

        return filter_var($sandbox, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) ?? false;
    }
}
