<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractMigration as SyliusAbstractMigration;

final class Version20200907102535 extends SyliusAbstractMigration
{
    public function getDescription(): string
    {
        return 'Add PayPalCredentials table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sylius_paypal_plugin_pay_pal_credentials (id VARCHAR(255) NOT NULL, payment_method_id INT DEFAULT NULL, access_token VARCHAR(255) NOT NULL, creation_time DATETIME NOT NULL, expiration_time DATETIME NOT NULL, INDEX IDX_C56F54AD5AA1164F (payment_method_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sylius_paypal_plugin_pay_pal_credentials ADD CONSTRAINT FK_C56F54AD5AA1164F FOREIGN KEY (payment_method_id) REFERENCES sylius_payment_method (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sylius_paypal_plugin_pay_pal_credentials');
    }
}
