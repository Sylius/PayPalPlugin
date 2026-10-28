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

use Sylius\Bundle\CoreBundle\SyliusCoreBundle;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class SyliusPayPalExtension extends Extension implements PrependExtensionInterface
{
    public const PAYPAL_FACTORY_NAME = 'sylius_paypal';

    public const PARTNER_ATTRIBUTION_ID = 'Sylius_MP_PPCP';

    private const PAYPAL_PARTNER_ID = 'PTV9W69CVKNEL';

    private const PAYPAL_PARTNER_CLIENT_ID = 'ARYYW7lvQsrqKS5TRaaOsH4x1MhT56KhIhY6zEzQPDtMWNLmnNbbapVQMke50UQp6JuWogzrxS_k66Fr';

    private const PAYPAL_SANDBOX_PARTNER_ID = 'JQVY284FYA5PC';

    private const PAYPAL_SANDBOX_PARTNER_CLIENT_ID = 'ARDYqDJaDpNYQZUlGxdR-AyNxVV-Xr24nrkcWLiJME9NSsl6w7l6GfVuKe5iVmCbysOtsjyGtVeXYM2a';

    private const PAYPAL_LOGO_URL = 'https://sylius.com/wp-content/uploads/2021/03/sylius-logo_sylius-logo-light-300x124.jpg';

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
        $container->setParameter('sylius_paypal.tracking.carriers', $config['tracking']['carriers']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.php');
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->setParameter(
            'sylius_paypal.admin.legacy_payment_method_list',
            version_compare(SyliusCoreBundle::VERSION, '2.3.0', '<'),
        );

        $this->prependDoctrineMapping($container);
        $this->prependApiPlatformMapping($container);

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

    private function prependApiPlatformMapping(ContainerBuilder $container): void
    {
        /** @var array<string, array<string, string>> $bundlesMetadata */
        $bundlesMetadata = $container->getParameter('kernel.bundles_metadata');
        if (!$container->hasExtension('api_platform') || !isset($bundlesMetadata['SyliusApiBundle'])) {
            return;
        }

        $container->prependExtensionConfig('api_platform', ['mapping' => ['paths' => [
            $bundlesMetadata['SyliusApiBundle']['path'] . '/Resources/config/api_platform',
            \dirname(__DIR__, 2) . '/config/api_platform',
        ]]]);
    }

    private function prependDoctrineMapping(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('doctrine')) {
            return;
        }

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'SyliusPayPalPluginPackageTracking' => [
                        'type' => 'attribute',
                        'dir' => \dirname(__DIR__) . '/PackageTracking/Entity',
                        'prefix' => 'Sylius\PayPalPlugin\PackageTracking\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);
    }

    private function setCommunicationParameters(ContainerBuilder $container, array $config): void
    {
        $container->setParameter('sylius_paypal.logging.increased', (bool) $config['logging']['increased']);
        $container->setParameter('sylius_paypal.test_buyer_country', $config['test_buyer_country']);
        $container->setParameter('sylius_paypal.prioritized_factory_name', self::PAYPAL_FACTORY_NAME);
        $container->setParameter('sylius_paypal.partner_attribution_id', self::PARTNER_ATTRIBUTION_ID);
        $container->setParameter('sylius_paypal.partner_credentials.partner_id', self::PAYPAL_PARTNER_ID);
        $container->setParameter('sylius_paypal.partner_credentials.partner_client_id', self::PAYPAL_PARTNER_CLIENT_ID);
        $container->setParameter('sylius_paypal.partner_credentials.logo_url', self::PAYPAL_LOGO_URL);
        $container->setParameter('sylius_paypal.partner_credentials.sandbox.partner_id', self::PAYPAL_SANDBOX_PARTNER_ID);
        $container->setParameter('sylius_paypal.partner_credentials.sandbox.partner_client_id', self::PAYPAL_SANDBOX_PARTNER_CLIENT_ID);
    }

    private function processEnvConfig(array $configs): array
    {
        $envConfig = [];

        $testBuyerCountryEnv = $_ENV['SYLIUS_PAYPAL_TEST_BUYER_COUNTRY'] ?? null;
        if ($testBuyerCountryEnv !== null) {
            $envConfig['test_buyer_country'] = $testBuyerCountryEnv;
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
