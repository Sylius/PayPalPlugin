<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SyliusPayPalExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $container->setParameter('sylius.paypal.logging.increased', (bool) $config['logging']['increased']);

        if ($config['sandbox']) {
            $container->setParameter('sylius.pay_pal.facilitator_url', 'https://paypal.sylius.com');
            $container->setParameter('sylius.pay_pal.api_base_url', 'https://api.sandbox.paypal.com/');
            $container->setParameter('sylius.pay_pal.reports_sftp_host', 'reports.sandbox.paypal.com');
        } else {
            $container->setParameter('sylius.pay_pal.facilitator_url', 'https://prod.paypal.sylius.com');
            $container->setParameter('sylius.pay_pal.api_base_url', 'https://api.paypal.com/');
            $container->setParameter('sylius.pay_pal.reports_sftp_host', 'reports.paypal.com');
        }

        $loader->load('services.xml');
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
                    'Sylius\PayPalPlugin\Migrations' => '@SyliusPayPalPlugin/Migrations',
                ]
            ),
        ]);

        $container->prependExtensionConfig('sylius_labs_doctrine_migrations_extra', [
            'migrations' => [
                'Sylius\PayPalPlugin\Migrations' => ['Sylius\Bundle\CoreBundle\Migrations'],
            ],
        ]);
    }
}
