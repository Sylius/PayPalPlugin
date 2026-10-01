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

namespace Tests\Sylius\PayPalPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class SyliusPayPalExtensionTest extends TestCase
{
    #[Test]
    public function it_adds_its_api_platform_mapping_after_the_sylius_one(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class() extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'api_platform';
            }
        });
        $container->prependExtensionConfig('api_platform', ['mapping' => ['paths' => ['/sylius/api_platform']]]);

        (new SyliusPayPalExtension())->prepend($container);

        $paths = array_merge(...array_map(
            static fn (array $config): array => $config['mapping']['paths'] ?? [],
            $container->getExtensionConfig('api_platform'),
        ));
        self::assertSame(['/sylius/api_platform', \dirname(__DIR__, 3) . '/config/api_platform'], $paths);
    }
}
