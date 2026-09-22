<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container) {
    $env = $_ENV['APP_ENV'] ?? 'dev';

    if (str_starts_with((string) $env, 'test')) {
        // Sylius 2.3 ships its Behat services as a PHP config, earlier versions only as XML,
        // which Symfony 8 can no longer load.
        $syliusBehatServices = '../../../vendor/sylius/sylius/src/Sylius/Behat/Resources/config/services';
        $container->import(is_file(__DIR__ . '/' . $syliusBehatServices . '.php') ? $syliusBehatServices . '.php' : $syliusBehatServices . '.xml');
        $container->import('@SyliusPayPalPlugin/tests/Behat/Resources/services.php');
        $container->import('@SyliusPayPalPlugin/tests/TestApplication/config/services_test.yaml');
    }
};
