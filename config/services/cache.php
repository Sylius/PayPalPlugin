<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sylius_paypal.cache')
        ->parent('cache.app')
        ->private()
        ->tag('cache.pool');
};
