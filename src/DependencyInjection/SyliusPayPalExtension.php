<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SyliusPayPalExtension extends Extension
{
    public function load(array $config, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration([], $container), $config);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $container->setParameter('sylius.paypal.logging.increased', $config['logging']['increased']);

        if ($config['sandbox']) {
            $container->setParameter('sylius.pay_pal.facilitator_url', 'https://sylius.local:8001');
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
}
