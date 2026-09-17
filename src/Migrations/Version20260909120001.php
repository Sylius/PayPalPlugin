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

final class Version20260909120001 extends AbstractPostgreSQLMigration
{
    public function getDescription(): string
    {
        return 'Add sylius_paypal_plugin_shipment_tracking table (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('sylius_paypal_plugin_shipment_tracking');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('shipment_id', 'integer', ['notnull' => true]);
        $table->addColumn('carrier', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('carrier_name_other', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('paypal_tracker_id', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('state', 'string', ['length' => 32]);
        $table->addColumn('attempts', 'integer', ['notnull' => true, 'default' => 0]);
        $table->addColumn('last_error', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['shipment_id'], 'UNIQ_paypal_shipment_tracking_shipment');
        $table->addForeignKeyConstraint(
            'sylius_shipment',
            ['shipment_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'FK_paypal_shipment_tracking_shipment',
        );
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('sylius_paypal_plugin_shipment_tracking');
    }
}
