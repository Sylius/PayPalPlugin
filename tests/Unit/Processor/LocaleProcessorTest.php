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

namespace Tests\Sylius\PayPalPlugin\Unit\Processor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Processor\LocaleProcessor;
use Sylius\PayPalPlugin\Resolver\SupportedLocaleResolverInterface;

final class LocaleProcessorTest extends TestCase
{
    #[Test]
    public function it_always_processes_locale_to_version_with_region(): void
    {
        $supportedLocaleResolver = $this->createMock(SupportedLocaleResolverInterface::class);
        $supportedLocaleResolver->method('resolve')->willReturnMap([
            ['et', 'et_EE'],
            ['pl', 'pl_PL'],
            ['ja', 'ja_JP'],
        ]);

        $localeProcessor = new LocaleProcessor($supportedLocaleResolver);

        self::assertEquals('et_EE', $localeProcessor->process('et'));
        self::assertEquals('pl_PL', $localeProcessor->process('pl'));
        self::assertEquals('ja_JP', $localeProcessor->process('ja'));
    }

    #[Test]
    public function it_returns_same_locale_if_it_is_valid(): void
    {
        $supportedLocaleResolver = $this->createMock(SupportedLocaleResolverInterface::class);
        $supportedLocaleResolver->method('resolve')->willReturnMap([
            ['it_IT', 'it_IT'],
            ['de_DE', 'de_DE'],
        ]);

        $localeProcessor = new LocaleProcessor($supportedLocaleResolver);

        self::assertEquals('it_IT', $localeProcessor->process('it_IT'));
        self::assertEquals('de_DE', $localeProcessor->process('de_DE'));
    }

    #[Test]
    public function it_returns_correct_locale_for_en_locale(): void
    {
        $supportedLocaleResolver = $this->createMock(SupportedLocaleResolverInterface::class);
        $supportedLocaleResolver->method('resolve')->willReturnMap([
            ['en', 'en_US'],
        ]);

        $localeProcessor = new LocaleProcessor($supportedLocaleResolver);

        self::assertEquals('en_US', $localeProcessor->process('en'));
    }
}
