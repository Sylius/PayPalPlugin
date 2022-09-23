<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

use Symfony\Component\Intl\Locales;

final class LocaleProcessor implements LocaleProcessorInterface
{
    public function process(string $locale): string
    {
        if ($this->isValidLocale($locale)) {
            return $locale;
        }

        if ($locale === 'en') {
            return 'en_US';
        }

        $locales = array_filter(Locales::getLocales(), function (string $targetLocale) use ($locale): bool {
            return
                strpos($targetLocale, $locale) === 0 &&
                strpos($targetLocale, '_') !== false &&
                strlen($targetLocale) === 5
            ;
        });

        if ([] === $locales) {
            throw new \UnexpectedValueException(sprintf('Locale "%s" is not supported by PayPal.', $locale));
        }

        return $locales[array_key_first($locales)];
    }

    private function isValidLocale(string $locale): bool
    {
        return strpos($locale, '_') !== false;
    }
}
