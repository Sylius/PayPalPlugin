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
use Sylius\PayPalPlugin\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    #[Test]
    public function it_provides_a_curated_carrier_list_by_default(): void
    {
        $carriers = $this->process([])['tracking']['carriers'];

        self::assertContains('FEDEX', $carriers);
        self::assertContains('INPOST_PACZKOMATY', $carriers);
        self::assertGreaterThan(30, count($carriers));
    }

    #[Test]
    public function it_replaces_the_default_carrier_list_with_the_configured_one(): void
    {
        $config = $this->process([
            ['tracking' => ['carriers' => ['INPOST_PACZKOMATY', 'DPD_POLAND', 'POCZTA_POLSKA']]],
        ]);

        self::assertSame(['INPOST_PACZKOMATY', 'DPD_POLAND', 'POCZTA_POLSKA'], $config['tracking']['carriers']);
    }

    #[Test]
    public function it_does_not_merge_carrier_lists_coming_from_several_configuration_sources(): void
    {
        $config = $this->process([
            ['tracking' => ['carriers' => ['FEDEX', 'UPS']]],
            ['tracking' => ['carriers' => ['INPOST_PACZKOMATY']]],
        ]);

        self::assertSame(['INPOST_PACZKOMATY'], $config['tracking']['carriers']);
    }

    /**
     * @param array<array-key, array<string, mixed>> $configs
     *
     * @return array<string, mixed>
     */
    private function process(array $configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }
}
