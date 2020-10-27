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

        $locales = Locales::getLocales();
        $localeFound = (int) array_search($locale, $locales);
        while (!$this->isValidLocale($locales[$localeFound]) && $localeFound < count($locales)) {
            ++$localeFound;
        }

        return $locales[$localeFound];
    }

    private function isValidLocale(string $locale): bool
    {
        return preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale) > 0;
    }
}
