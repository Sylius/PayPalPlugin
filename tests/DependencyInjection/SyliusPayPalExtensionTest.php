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

namespace Tests\Sylius\PayPalPlugin\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;

final class SyliusPayPalExtensionTest extends AbstractExtensionTestCase
{
    private ?string $originalLoggingEnv = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setParameter('kernel.bundles_metadata', []);
        $this->saveEnvVars();
        $this->clearEnvVars();
    }

    protected function tearDown(): void
    {
        $this->restoreEnvVars();
        parent::tearDown();
    }

    protected function getContainerExtensions(): array
    {
        return [new SyliusPayPalExtension()];
    }

    public function test_it_loads_services_on_load(): void
    {
        $this->expectNotToPerformAssertions();

        $this->load();
    }

    public function test_it_increases_logging_via_env(): void
    {
        $this->setEnvVar('SYLIUS_PAYPAL_LOGGING_INCREASED', 'true');

        $this->load();

        $this->assertContainerBuilderHasParameter('sylius_paypal.logging.increased', true);
    }

    public function test_it_sets_the_production_and_sandbox_partner_credentials(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('sylius_paypal.partner_credentials.partner_id', 'PTV9W69CVKNEL');
        $this->assertContainerBuilderHasParameter('sylius_paypal.partner_credentials.sandbox.partner_id', 'JQVY284FYA5PC');
        $this->assertContainerBuilderHasParameter(
            'sylius_paypal.partner_credentials.sandbox.partner_client_id',
            'ARDYqDJaDpNYQZUlGxdR-AyNxVV-Xr24nrkcWLiJME9NSsl6w7l6GfVuKe5iVmCbysOtsjyGtVeXYM2a',
        );
    }

    private function saveEnvVars(): void
    {
        $this->originalLoggingEnv = $_ENV['SYLIUS_PAYPAL_LOGGING_INCREASED'] ?? null;
    }

    private function clearEnvVars(): void
    {
        unset($_ENV['SYLIUS_PAYPAL_LOGGING_INCREASED']);
    }

    private function restoreEnvVars(): void
    {
        if ($this->originalLoggingEnv !== null) {
            $_ENV['SYLIUS_PAYPAL_LOGGING_INCREASED'] = $this->originalLoggingEnv;
        } else {
            unset($_ENV['SYLIUS_PAYPAL_LOGGING_INCREASED']);
        }
    }

    private function setEnvVar(string $name, string $value): void
    {
        $_ENV[$name] = $value;
    }
}
