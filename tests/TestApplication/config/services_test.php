<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container) {
    $env = $_ENV['APP_ENV'] ?? 'dev';

    if (str_starts_with($env, 'test')) {
        $container->import('../../../vendor/sylius/sylius/src/Sylius/Behat/Resources/config/services.xml');
        $container->import('@SyliusPayPalPlugin/tests/Behat/Resources/services.php');
        $container->import('@SyliusPayPalPlugin/tests/TestApplication/config/services_test.yaml');
    }
};
