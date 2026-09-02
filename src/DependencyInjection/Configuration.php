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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    private const ALL_PAYPAL_SUPPORTED_LOCALES = [
        'ar_EG', 'cs_CZ', 'da_DK', 'de_DE', 'el_GR', 'en_US', 'en_AU', 'en_GB',
        'en_IN', 'es_ES', 'es_XC', 'fi_FI', 'fr_FR', 'fr_CA', 'fr_XC', 'he_IL',
        'hu_HU', 'id_ID', 'it_IT', 'ja_JP', 'ko_KR', 'nl_NL', 'no_NO', 'pl_PL',
        'pt_BR', 'pt_PT', 'ru_RU', 'sk_SK', 'sv_SE', 'th_TH', 'zh_CN', 'zh_HK',
        'zh_TW', 'zh_XC',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sylius_paypal');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('logging')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('increased')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('supported_locales')
                    ->scalarPrototype()->end()
                    ->defaultValue(self::ALL_PAYPAL_SUPPORTED_LOCALES)
                    ->performNoDeepMerging()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
