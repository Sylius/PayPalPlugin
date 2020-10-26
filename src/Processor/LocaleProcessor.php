<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

final class LocaleProcessor implements LocaleProcessorInterface
{
    public function process(string $locale): string
    {
        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale)) {
            return $locale;
        }

        return $locale . '_' . strtoupper($locale);
    }
}
