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
        return 'Add ShipmentTracking table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE sylius_paypal_plugin_shipment_tracking_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE sylius_paypal_plugin_shipment_tracking (id INT NOT NULL, shipment_id INT NOT NULL, carrier VARCHAR(255) DEFAULT NULL, carrier_name_other VARCHAR(255) DEFAULT NULL, paypal_tracker_id VARCHAR(255) DEFAULT NULL, state VARCHAR(32) NOT NULL, attempts INT DEFAULT 0 NOT NULL, last_error TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D2A2F8D67BE036FC ON sylius_paypal_plugin_shipment_tracking (shipment_id)');
        $this->addSql('ALTER TABLE sylius_paypal_plugin_shipment_tracking ADD CONSTRAINT FK_D2A2F8D67BE036FC FOREIGN KEY (shipment_id) REFERENCES sylius_shipment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_paypal_plugin_shipment_tracking DROP CONSTRAINT FK_D2A2F8D67BE036FC');
        $this->addSql('DROP TABLE sylius_paypal_plugin_shipment_tracking');
        $this->addSql('DROP SEQUENCE sylius_paypal_plugin_shipment_tracking_id_seq');
    }
}
