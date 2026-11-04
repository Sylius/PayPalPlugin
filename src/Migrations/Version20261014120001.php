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

final class Version20261014120001 extends AbstractPostgreSQLMigration
{
    public function getDescription(): string
    {
        return 'Keep one PayPal access token per payment method';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM sylius_paypal_plugin_pay_pal_credentials');
        $this->addSql('DROP INDEX IDX_C56F54AD5AA1164F');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C56F54AD5AA1164F ON sylius_paypal_plugin_pay_pal_credentials (payment_method_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_C56F54AD5AA1164F');
        $this->addSql('CREATE INDEX IDX_C56F54AD5AA1164F ON sylius_paypal_plugin_pay_pal_credentials (payment_method_id)');
    }
}
