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
     * @dataProvider productionCommunicationParametersProvider
     */
    public function test_it_always_sets_production_communication_parameters(
        string $parameterName,
        string $expectedValue,
    ): void {
        $this->load();

        $this->assertContainerBuilderHasParameter($parameterName, $expectedValue);
    }

    public static function productionCommunicationParametersProvider(): iterable
    {
        yield 'api base url' => [
            'sylius_paypal.api_base_url',
            'https://api.paypal.com/',
        ];

        yield 'web url' => [
            'sylius_paypal.web_url',
            'https://www.paypal.com',
        ];

        yield 'partner js url' => [
            'sylius_paypal.partner_js_url',
            'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js',
        ];
    }

    public function test_it_increases_logging_via_env(): void
    {
        $this->setEnvVar('SYLIUS_PAYPAL_LOGGING_INCREASED', 'true');

        $this->load();

        $this->assertContainerBuilderHasParameter('sylius_paypal.logging.increased', true);
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
