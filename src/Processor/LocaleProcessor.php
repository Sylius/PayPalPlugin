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

namespace Sylius\PayPalPlugin\Processor;

use Sylius\PayPalPlugin\Resolver\SupportedLocaleResolverInterface;

final class LocaleProcessor implements LocaleProcessorInterface
{
    private const ALLOWED_LOCALE_PATTERN = '/^[a-z]{2}(_[A-Z]{2})?$/';

    public function __construct(private readonly SupportedLocaleResolverInterface $supportedLocaleResolver)
    {
    }

    public function process(string $locale): string
    {
        $locale = trim($locale);

        if ($this->isValidLocale($locale)) {
            return $this->supportedLocaleResolver->resolve($locale);
        }

        throw new \UnexpectedValueException(sprintf('Locale "%s" is not valid.', $locale));
    }

    private function isValidLocale(string $locale): bool
    {
        return
            false === str_contains($locale, ' ') &&
            1 === preg_match(self::ALLOWED_LOCALE_PATTERN, $locale)
        ;
    }
}
