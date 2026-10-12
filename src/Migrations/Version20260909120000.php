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

final class Version20260909120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ShipmentTracking table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sylius_paypal_plugin_shipment_tracking (id INT AUTO_INCREMENT NOT NULL, shipment_id INT NOT NULL, carrier VARCHAR(255) DEFAULT NULL, carrier_name_other VARCHAR(255) DEFAULT NULL, paypal_tracker_id VARCHAR(255) DEFAULT NULL, state VARCHAR(32) NOT NULL, attempts INT DEFAULT 0 NOT NULL, last_error LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_D2A2F8D67BE036FC (shipment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sylius_paypal_plugin_shipment_tracking ADD CONSTRAINT FK_D2A2F8D67BE036FC FOREIGN KEY (shipment_id) REFERENCES sylius_shipment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sylius_paypal_plugin_shipment_tracking');
    }
}
