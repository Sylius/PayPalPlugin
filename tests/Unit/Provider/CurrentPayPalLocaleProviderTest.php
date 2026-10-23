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

namespace Tests\Sylius\PayPalPlugin\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Context\LocaleNotFoundException;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Sylius\PayPalPlugin\Provider\CurrentPayPalLocaleProvider;

final class CurrentPayPalLocaleProviderTest extends TestCase
{
    public function test_it_provides_the_current_locale_as_paypal_expects_it(): void
    {
        $localeContext = $this->createStub(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willReturn('pl');
        $localeProcessor = $this->createStub(LocaleProcessorInterface::class);
        $localeProcessor->method('process')->willReturnMap([['pl', 'pl_PL']]);

        self::assertSame('pl_PL', (new CurrentPayPalLocaleProvider($localeContext, $localeProcessor))->provide());
    }

    public function test_it_provides_no_locale_when_paypal_does_not_support_the_current_one(): void
    {
        $localeContext = $this->createStub(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willReturn('es_MX');
        $localeProcessor = $this->createStub(LocaleProcessorInterface::class);
        $localeProcessor->method('process')->willThrowException(new \UnexpectedValueException('Locale "es_MX" is not supported by PayPal.'));

        self::assertNull((new CurrentPayPalLocaleProvider($localeContext, $localeProcessor))->provide());
    }

    public function test_it_provides_no_locale_when_there_is_no_current_one(): void
    {
        $localeContext = $this->createStub(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willThrowException(new LocaleNotFoundException());

        self::assertNull((new CurrentPayPalLocaleProvider($localeContext, $this->createStub(LocaleProcessorInterface::class)))->provide());
    }
}
