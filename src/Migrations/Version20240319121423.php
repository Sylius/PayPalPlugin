<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractPostgreSQLMigration as SyliusAbstractMigration;

if (class_exists(SyliusAbstractMigration::class)) {
    final class Version20240319121423 extends SyliusAbstractMigration
    {
        public function getDescription(): string
        {
            return 'Add PayPalCredentials table';
        }

        public function up(Schema $schema): void
        {
            $this->addSql('CREATE TABLE sylius_paypal_plugin_pay_pal_credentials (id VARCHAR(255) NOT NULL, payment_method_id INT DEFAULT NULL, access_token VARCHAR(255) NOT NULL, creation_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expiration_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE INDEX IDX_C56F54AD5AA1164F ON sylius_paypal_plugin_pay_pal_credentials (payment_method_id)');
            $this->addSql('ALTER TABLE sylius_paypal_plugin_pay_pal_credentials ADD CONSTRAINT FK_C56F54AD5AA1164F FOREIGN KEY (payment_method_id) REFERENCES sylius_payment_method (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        }

        public function down(Schema $schema): void
        {
            $this->addSql('ALTER TABLE sylius_paypal_plugin_pay_pal_credentials DROP CONSTRAINT FK_C56F54AD5AA1164F');
            $this->addSql('DROP TABLE sylius_paypal_plugin_pay_pal_credentials');
        }
    }
}
