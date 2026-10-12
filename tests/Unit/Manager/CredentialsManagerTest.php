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

namespace Tests\Sylius\PayPalPlugin\Unit\Manager;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Manager\CredentialsManager;

final class CredentialsManagerTest extends TestCase
{
    private CredentialsManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new CredentialsManager();
    }

    #[Test]
    public function it_stores_credentials_on_a_new_config(): void
    {
        $config = $this->manager->store(
            ['use_authorize' => true],
            false,
            ['client_id' => 'PROD', 'client_secret' => 'PRODS', 'foo' => 'bar'],
        );

        self::assertFalse($config['sandbox']);
        self::assertSame('PROD', $config['client_id']);
        self::assertSame('PRODS', $config['client_secret']);
        self::assertNull($config['merchant_id']);
        self::assertTrue($config['use_authorize']);
        self::assertArrayNotHasKey('foo', $config);
        self::assertSame(['client_id' => 'PROD', 'client_secret' => 'PRODS'], $config['production_credentials']);
        self::assertArrayNotHasKey('sandbox_credentials', $config);
    }

    #[Test]
    public function it_keeps_the_production_credentials_of_a_config_without_mode_when_storing_sandbox_credentials(): void
    {
        $config = $this->manager->store(
            ['client_id' => 'PROD', 'client_secret' => 'PRODS', 'merchant_id' => 'PRODM', 'use_authorize' => true],
            true,
            ['client_id' => 'SB', 'client_secret' => 'SBS'],
        );

        self::assertTrue($config['sandbox']);
        self::assertSame('SB', $config['client_id']);
        self::assertSame('SBS', $config['client_secret']);
        self::assertNull($config['merchant_id']);
        self::assertSame(
            ['client_id' => 'PROD', 'client_secret' => 'PRODS', 'merchant_id' => 'PRODM'],
            $config['production_credentials'],
        );
        self::assertSame(['client_id' => 'SB', 'client_secret' => 'SBS'], $config['sandbox_credentials']);
    }

    #[Test]
    public function it_overwrites_the_vault_of_the_mode_being_stored(): void
    {
        $config = $this->manager->store(
            $this->bothModesConfig(),
            false,
            ['client_id' => 'NEW_PROD', 'client_secret' => 'NEW_PRODS', 'merchant_id' => 'NEW_PRODM'],
        );

        self::assertFalse($config['sandbox']);
        self::assertSame('NEW_PROD', $config['client_id']);
        self::assertSame(
            ['client_id' => 'NEW_PROD', 'client_secret' => 'NEW_PRODS', 'merchant_id' => 'NEW_PRODM'],
            $config['production_credentials'],
        );
        self::assertSame('SB', $config['sandbox_credentials']['client_id']);
    }

    #[Test]
    public function it_switches_to_the_other_mode_snapshotting_the_active_one(): void
    {
        $activeConfig = $this->bothModesConfig();
        $activeConfig['webhook_id'] = 'PROD_WEBHOOK';

        $config = $this->manager->switchTo($activeConfig, true);

        self::assertTrue($config['sandbox']);
        self::assertSame('SB', $config['client_id']);
        self::assertSame('SBS', $config['client_secret']);
        self::assertSame('SBM', $config['merchant_id']);
        self::assertNull($config['webhook_id']);
        self::assertSame('PROD_WEBHOOK', $config['production_credentials']['webhook_id']);
    }

    #[Test]
    public function it_restores_the_production_credentials_of_a_config_without_mode_after_switching_back(): void
    {
        $config = $this->manager->store(
            ['client_id' => 'PROD', 'client_secret' => 'PRODS'],
            true,
            ['client_id' => 'SB', 'client_secret' => 'SBS'],
        );

        $config = $this->manager->switchTo($config, false);

        self::assertFalse($config['sandbox']);
        self::assertSame('PROD', $config['client_id']);
        self::assertSame('PRODS', $config['client_secret']);
        self::assertSame(['client_id' => 'SB', 'client_secret' => 'SBS'], $config['sandbox_credentials']);
    }

    #[Test]
    public function it_clears_the_active_credentials_when_switching_to_a_mode_without_a_vault(): void
    {
        $config = $this->manager->switchTo(['sandbox' => false, 'client_id' => 'PROD', 'client_secret' => 'PRODS'], true);

        self::assertTrue($config['sandbox']);
        self::assertNull($config['client_id']);
        self::assertNull($config['client_secret']);
        self::assertSame(['client_id' => 'PROD', 'client_secret' => 'PRODS'], $config['production_credentials']);
    }

    #[Test]
    public function it_checks_whether_a_mode_has_credentials(): void
    {
        $config = $this->bothModesConfig();
        $config['sandbox_credentials']['client_id'] = '';

        self::assertTrue($this->manager->hasCredentials($config, false));
        self::assertFalse($this->manager->hasCredentials($config, true));
        self::assertFalse($this->manager->hasCredentials([], false));
    }

    #[Test]
    public function it_checks_whether_the_active_mode_of_a_config_without_vaults_has_credentials(): void
    {
        self::assertTrue($this->manager->hasCredentials(['client_id' => 'PROD'], false));
        self::assertFalse($this->manager->hasCredentials(['client_id' => 'PROD'], true));
        self::assertTrue($this->manager->hasCredentials(['sandbox' => true, 'client_id' => 'SB'], true));
        self::assertFalse($this->manager->hasCredentials(['sandbox' => true, 'client_id' => 'SB'], false));
        self::assertFalse($this->manager->hasCredentials(['sandbox' => false, 'client_id' => ''], false));
    }

    /**
     * @return array<string, mixed>
     */
    private function bothModesConfig(): array
    {
        return [
            'sandbox' => false,
            'client_id' => 'PROD',
            'client_secret' => 'PRODS',
            'merchant_id' => 'PRODM',
            'production_credentials' => ['client_id' => 'PROD', 'client_secret' => 'PRODS', 'merchant_id' => 'PRODM'],
            'sandbox_credentials' => ['client_id' => 'SB', 'client_secret' => 'SBS', 'merchant_id' => 'SBM'],
        ];
    }
}
