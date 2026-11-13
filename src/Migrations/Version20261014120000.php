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
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractMigration;

final class Version20261014120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep one PayPal access token per payment method';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM sylius_paypal_plugin_pay_pal_credentials');
        $this->addSql('ALTER TABLE sylius_paypal_plugin_pay_pal_credentials DROP INDEX IDX_C56F54AD5AA1164F, ADD UNIQUE INDEX UNIQ_C56F54AD5AA1164F (payment_method_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_paypal_plugin_pay_pal_credentials DROP INDEX UNIQ_C56F54AD5AA1164F, ADD INDEX IDX_C56F54AD5AA1164F (payment_method_id)');
    }
}
