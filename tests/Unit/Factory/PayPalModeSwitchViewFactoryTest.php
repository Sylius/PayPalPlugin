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

namespace Tests\Sylius\PayPalPlugin\Unit\Factory;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Factory\PayPalModeSwitchViewFactory;

final class PayPalModeSwitchViewFactoryTest extends TestCase
{
    private PayPalModeSwitchViewFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new PayPalModeSwitchViewFactory();
    }

    #[Test]
    public function it_treats_an_empty_config_as_an_unconfigured_production_method(): void
    {
        $view = $this->factory->createFromConfig([]);

        self::assertSame('production', $view->currentMode);
        self::assertFalse($view->isSandboxMode());
        self::assertFalse($view->productionConfigured);
        self::assertFalse($view->sandboxConfigured);
        self::assertSame([
            'sandbox' => ['client_id' => '', 'client_secret' => ''],
            'production' => ['client_id' => '', 'client_secret' => ''],
        ], $view->credentialsPreview);
    }

    #[Test]
    public function it_maps_a_legacy_production_method_from_top_level_credentials(): void
    {
        $view = $this->factory->createFromConfig([
            'sandbox' => false,
            'client_id' => 'PROD-ID',
            'client_secret' => 'PROD-SECRET',
        ]);

        self::assertSame('production', $view->currentMode);
        self::assertTrue($view->productionConfigured);
        self::assertFalse($view->sandboxConfigured);
        self::assertSame(['client_id' => 'PROD-ID', 'client_secret' => 'PROD-SECRET'], $view->credentialsPreview['production']);
        self::assertSame(['client_id' => '', 'client_secret' => ''], $view->credentialsPreview['sandbox']);
    }

    #[Test]
    public function it_maps_a_legacy_sandbox_method_from_top_level_credentials(): void
    {
        $view = $this->factory->createFromConfig([
            'sandbox' => true,
            'client_id' => 'SB-ID',
            'client_secret' => 'SB-SECRET',
        ]);

        self::assertSame('sandbox', $view->currentMode);
        self::assertTrue($view->isSandboxMode());
        self::assertTrue($view->sandboxConfigured);
        self::assertFalse($view->productionConfigured);
        self::assertSame(['client_id' => 'SB-ID', 'client_secret' => 'SB-SECRET'], $view->credentialsPreview['sandbox']);
        self::assertSame(['client_id' => '', 'client_secret' => ''], $view->credentialsPreview['production']);
    }

    #[Test]
    public function it_prefers_the_stored_vaults_over_the_active_top_level_credentials(): void
    {
        $view = $this->factory->createFromConfig([
            'sandbox' => false,
            'client_id' => 'PROD-ID',
            'client_secret' => 'PROD-SECRET',
            'production_credentials' => ['client_id' => 'VAULT-PROD-ID', 'client_secret' => 'VAULT-PROD-SECRET'],
            'sandbox_credentials' => ['client_id' => 'VAULT-SB-ID', 'client_secret' => 'VAULT-SB-SECRET'],
        ]);

        self::assertTrue($view->productionConfigured);
        self::assertTrue($view->sandboxConfigured);
        self::assertSame(['client_id' => 'VAULT-PROD-ID', 'client_secret' => 'VAULT-PROD-SECRET'], $view->credentialsPreview['production']);
        self::assertSame(['client_id' => 'VAULT-SB-ID', 'client_secret' => 'VAULT-SB-SECRET'], $view->credentialsPreview['sandbox']);
    }

    #[Test]
    public function it_reports_the_inactive_mode_as_configured_when_only_its_vault_exists(): void
    {
        $view = $this->factory->createFromConfig([
            'sandbox' => false,
            'client_id' => 'PROD-ID',
            'client_secret' => 'PROD-SECRET',
            'sandbox_credentials' => ['client_id' => 'VAULT-SB-ID', 'client_secret' => 'VAULT-SB-SECRET'],
        ]);

        self::assertSame('production', $view->currentMode);
        self::assertTrue($view->sandboxConfigured);
        self::assertSame(['client_id' => 'VAULT-SB-ID', 'client_secret' => 'VAULT-SB-SECRET'], $view->credentialsPreview['sandbox']);
    }
}
