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
    private const ENV_VARS = ['SYLIUS_PAYPAL_LOGGING_INCREASED', 'SYLIUS_PAYPAL_SANDBOX_ENABLED'];

    /** @var array<string, string> */
    private array $originalEnvVars = [];

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

    public function test_it_resolves_the_sandbox_mode_of_2_x_to_sandbox_by_default(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('.sylius_paypal.sandbox', true);
    }

    public function test_it_resolves_the_sandbox_mode_from_the_configuration(): void
    {
        $this->loadCollectingDeprecations(['sandbox' => false]);

        $this->assertContainerBuilderHasParameter('.sylius_paypal.sandbox', false);
    }

    public function test_it_resolves_the_sandbox_mode_from_the_env_over_the_configuration(): void
    {
        $this->setEnvVar('SYLIUS_PAYPAL_SANDBOX_ENABLED', 'false');

        $this->loadCollectingDeprecations(['sandbox' => true]);

        $this->assertContainerBuilderHasParameter('.sylius_paypal.sandbox', false);
    }

    public function test_it_deprecates_the_sandbox_option(): void
    {
        $deprecations = $this->loadCollectingDeprecations(['sandbox' => true]);

        self::assertCount(1, $deprecations);
        self::assertStringContainsString('"sylius_paypal.sandbox" option is deprecated', $deprecations[0]);
    }

    public function test_it_does_not_deprecate_anything_without_the_sandbox_option(): void
    {
        self::assertSame([], $this->loadCollectingDeprecations());
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

    /** @param array<string, mixed> $config */
    private function loadCollectingDeprecations(array $config = []): array
    {
        $deprecations = [];
        set_error_handler(static function (int $errno, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $this->load($config);
        } finally {
            restore_error_handler();
        }

        return $deprecations;
    }

    private function saveEnvVars(): void
    {
        foreach (self::ENV_VARS as $name) {
            if (isset($_ENV[$name])) {
                $this->originalEnvVars[$name] = $_ENV[$name];
            }
        }
    }

    private function clearEnvVars(): void
    {
        foreach (self::ENV_VARS as $name) {
            unset($_ENV[$name]);
        }
    }

    private function restoreEnvVars(): void
    {
        $this->clearEnvVars();

        foreach ($this->originalEnvVars as $name => $value) {
            $_ENV[$name] = $value;
        }
    }

    private function setEnvVar(string $name, string $value): void
    {
        $_ENV[$name] = $value;
    }
}
