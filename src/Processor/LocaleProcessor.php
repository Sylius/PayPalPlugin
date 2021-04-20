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

        $locales = array_filter(Locales::getLocales(), function (string $targetLocale) use ($locale): bool {
            return
                strpos($targetLocale, $locale) === 0 &&
                strpos($targetLocale, '_') !== false &&
                strlen($targetLocale) === 5
            ;
        });

        return preg_replace('/en_[A-Z]{2}/', 'en_US', $locales[array_key_first($locales)]);
    }

    private function isValidLocale(string $locale): bool
    {
        return strpos($locale, '_') !== false;
    }
}
