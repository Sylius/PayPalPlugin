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

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;

final readonly class CurrentPayPalLocaleProvider implements CurrentPayPalLocaleProviderInterface
{
    public function __construct(
        private LocaleContextInterface $localeContext,
        private LocaleProcessorInterface $localeProcessor,
    ) {
    }

    public function provide(): ?string
    {
        try {
            return $this->localeProcessor->process($this->localeContext->getLocaleCode());
        } catch (\RuntimeException) {
            return null;
        }
    }
}
