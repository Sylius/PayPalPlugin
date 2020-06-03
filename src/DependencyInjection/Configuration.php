<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sylius_paypal_plugin');
        $rootNode = $treeBuilder->getRootNode();

        return $treeBuilder;
    }
}
