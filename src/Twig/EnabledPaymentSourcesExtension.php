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

namespace Sylius\PayPalPlugin\Twig;

use Sylius\PayPalPlugin\Provider\EnabledPaymentSourcesProviderInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EnabledPaymentSourcesExtension extends AbstractExtension
{
    public function __construct(private readonly EnabledPaymentSourcesProviderInterface $enabledPaymentSourcesProvider)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sylius_paypal_enabled_payment_sources', [$this->enabledPaymentSourcesProvider, 'provide']),
        ];
    }
}
