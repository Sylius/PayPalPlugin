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

namespace Sylius\PayPalPlugin\DependencyInjection;

use Sylius\PayPalPlugin\Creator\PayPalSandboxPaymentMethodCreatorInterface;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SyliusPayPalExtension extends Extension implements PrependExtensionInterface
{
    public const PAYPAL_FACTORY_NAME = 'sylius_paypal';

    public function getAlias(): string
    {
        return 'sylius_paypal';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configs = $this->processEnvConfig($configs);
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);

        $this->setCommunicationParameters($container, $config);

        $container->setParameter('sylius_paypal.supported_locales', $config['supported_locales']);

        $loaderResolver = new LoaderResolver([
            new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config')),
            new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config')),
        ]);
        $delegatingLoader = new DelegatingLoader($loaderResolver);

        $delegatingLoader->load('services.xml');
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }

    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('doctrine_migrations') || !$container->hasExtension('sylius_labs_doctrine_migrations_extra')) {
            return;
        }

        if (
            $container->hasParameter('sylius_core.prepend_doctrine_migrations') &&
            !$container->getParameter('sylius_core.prepend_doctrine_migrations')
        ) {
            return;
        }

        /** @var array<int|string, mixed> $doctrineConfig */
        $doctrineConfig = $container->getExtensionConfig('doctrine_migrations');
        $migrationsPath = (array) \array_pop($doctrineConfig)['migrations_paths'];
        $container->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => \array_merge(
                $migrationsPath,
                [
                    'Sylius\PayPalPlugin\Migrations' => '@SyliusPayPalPlugin/src/Migrations',
                ],
            ),
        ]);

        $container->prependExtensionConfig('sylius_labs_doctrine_migrations_extra', [
            'migrations' => [
                'Sylius\PayPalPlugin\Migrations' => ['Sylius\Bundle\CoreBundle\Migrations'],
            ],
        ]);
    }

    private function setCommunicationParameters(ContainerBuilder $container, array $config): void
    {
        $container->setParameter('sylius_paypal.logging.increased', (bool) $config['logging']['increased']);
        $container->setParameter('sylius_paypal.sandbox', (bool) $config['sandbox']);
        $container->setParameter('sylius_paypal.prioritized_factory_name', self::PAYPAL_FACTORY_NAME);
        $container->setParameter('sylius_paypal.partner_attribution_id', PayPalSandboxPaymentMethodCreatorInterface::PARTNER_ATTRIBUTION_ID);

        if ($container->getParameter('sylius_paypal.sandbox')) {
            $container->setParameter('sylius_paypal.api_base_url', 'https://api.sandbox.paypal.com/');
            $container->setParameter('sylius_paypal.reports_sftp_host', 'reports.sandbox.paypal.com');
            $container->setParameter('sylius_paypal.web_url', 'https://www.sandbox.paypal.com');
            $container->setParameter('sylius_paypal.partner_js_url', 'https://www.sandbox.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js');
            $partnerCredentialsUrl = 'https://paypal.sylius.com/partner-credentials';
        } else {
            $container->setParameter('sylius_paypal.api_base_url', 'https://api.paypal.com/');
            $container->setParameter('sylius_paypal.reports_sftp_host', 'reports.paypal.com');
            $container->setParameter('sylius_paypal.web_url', 'https://www.paypal.com');
            $container->setParameter('sylius_paypal.partner_js_url', 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js');
            $partnerCredentialsUrl = 'https://prod.paypal.sylius.com/partner-credentials';
        }

        // TODO: remove once the real partner-credentials endpoint is in place.
        $container->setParameter(
            'sylius_paypal.partner_credentials_url',
            $_ENV['SYLIUS_PAYPAL_PARTNER_CREDENTIALS_URL'] ?? $partnerCredentialsUrl,
        );

        // partner_id/partner_client_id are static and identical for every store; configuring these lets
        // onboarding survive a failing/slow partner-credentials call instead of hard-failing.
        $container->setParameter(
            'sylius_paypal.partner_credentials.fallback_partner_id',
            $_ENV['SYLIUS_PAYPAL_FALLBACK_PARTNER_ID'] ?? '',
        );
        $container->setParameter(
            'sylius_paypal.partner_credentials.fallback_partner_client_id',
            $_ENV['SYLIUS_PAYPAL_FALLBACK_PARTNER_CLIENT_ID'] ?? '',
        );
    }

    private function processEnvConfig(array $configs): array
    {
        $envConfig = [];

        $sandboxEnv = $_ENV['SYLIUS_PAYPAL_SANDBOX_ENABLED'] ?? null;
        if ($sandboxEnv !== null) {
            $envConfig['sandbox'] = filter_var($sandboxEnv, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) ?? false;
        }

        $loggingEnv = $_ENV['SYLIUS_PAYPAL_LOGGING_INCREASED'] ?? null;
        if ($loggingEnv !== null) {
            $envConfig['logging'] = ['increased' => filter_var($loggingEnv, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) ?? false];
        }

        if ([] !== $envConfig) {
            $configs[] = $envConfig;
        }

        return $configs;
    }
}
