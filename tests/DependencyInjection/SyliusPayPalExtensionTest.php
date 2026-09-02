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
    private ?string $originalSandboxEnv = null;

    private ?string $originalLoggingEnv = null;

    protected function setUp(): void
    {
        parent::setUp();
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

    /**
     * @dataProvider sandboxModeParametersProvider
     */
    public function test_it_sets_parameters_based_on_sandbox_mode(
        bool $sandboxEnabled,
        string $parameterName,
        string $expectedValue,
    ): void {
        $this->load(['sandbox' => $sandboxEnabled]);

        $this->assertContainerBuilderHasParameter($parameterName, $expectedValue);
    }

    public static function sandboxModeParametersProvider(): iterable
    {
        yield 'production mode api base url' => [
            false,
            'sylius_paypal.api_base_url',
            'https://api.paypal.com/',
        ];

        yield 'sandbox mode api base url' => [
            true,
            'sylius_paypal.api_base_url',
            'https://api.sandbox.paypal.com/',
        ];

        yield 'production mode sftp host' => [
            false,
            'sylius.pay_pal.reports_sftp_host',
            'reports.paypal.com',
        ];

        yield 'sandbox mode sftp host' => [
            true,
            'sylius.pay_pal.reports_sftp_host',
            'reports.sandbox.paypal.com',
        ];

        yield 'production mode aliased api base url' => [
            false,
            'sylius.pay_pal.api_base_url',
            'https://api.paypal.com/',
        ];

        yield 'sandbox mode aliased api base url' => [
            true,
            'sylius.pay_pal.api_base_url',
            'https://api.sandbox.paypal.com/',
        ];

        yield 'production mode aliased sftp host' => [
            false,
            'sylius.pay_pal.reports_sftp_host',
            'reports.paypal.com',
        ];

        yield 'sandbox mode aliased sftp host' => [
            true,
            'sylius.pay_pal.reports_sftp_host',
            'reports.sandbox.paypal.com',
        ];
    }

    /**
     * @dataProvider environmentVariablesProvider
     */
    public function test_it_respects_environment_variables(
        string $envVarName,
        string $envVarValue,
        string $parameterName,
        mixed $expectedValue,
    ): void {
        $this->setEnvVar($envVarName, $envVarValue);

        $this->load();

        $this->assertContainerBuilderHasParameter($parameterName, $expectedValue);
    }

    public static function environmentVariablesProvider(): iterable
    {
        yield 'sandbox mode disabled via env' => [
            'SYLIUS_PAYPAL_SANDBOX_ENABLED',
            'false',
            'sylius_paypal.api_base_url',
            'https://api.paypal.com/',
        ];

        yield 'logging increased enabled via env' => [
            'SYLIUS_PAYPAL_LOGGING_INCREASED',
            'true',
            'sylius_paypal.logging.increased',
            true,
        ];
    }

    private function saveEnvVars(): void
    {
        $this->originalSandboxEnv = $_ENV['SYLIUS_PAYPAL_SANDBOX_ENABLED'] ?? null;
        $this->originalLoggingEnv = $_ENV['SYLIUS_PAYPAL_LOGGING_INCREASED'] ?? null;
    }

    private function clearEnvVars(): void
    {
        unset($_ENV['SYLIUS_PAYPAL_SANDBOX_ENABLED'], $_ENV['SYLIUS_PAYPAL_LOGGING_INCREASED']);
    }

    private function restoreEnvVars(): void
    {
        if ($this->originalSandboxEnv !== null) {
            $_ENV['SYLIUS_PAYPAL_SANDBOX_ENABLED'] = $this->originalSandboxEnv;
        } else {
            unset($_ENV['SYLIUS_PAYPAL_SANDBOX_ENABLED']);
        }
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
