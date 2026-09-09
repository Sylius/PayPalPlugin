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

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PayPalShippingCallbackUrlProvider implements PayPalShippingCallbackUrlProviderInterface
{
    private const REQUIRED_SCHEME = 'https';

    public function __construct(
        private UrlGeneratorInterface $router,
        private string $route = 'sylius_paypal_shop_order_shipping_callback',
    ) {
    }

    public function provide(): ?string
    {
        $callbackUrl = $this->router->generate($this->route, [], UrlGeneratorInterface::ABSOLUTE_URL);

        if (!str_starts_with($callbackUrl, self::REQUIRED_SCHEME . '://')) {
            return null;
        }

        return $callbackUrl;
    }
}
