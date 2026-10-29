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

final class Version20261015120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add PayPalWebhookEvent table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sylius_paypal_plugin_webhook_event (event_id VARCHAR(255) NOT NULL, payment_method_code VARCHAR(255) NOT NULL, event_type VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_1D2215708B8E8428 (created_at), PRIMARY KEY(event_id, payment_method_code)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sylius_paypal_plugin_webhook_event');
    }
}
