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

use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ShippingCallbackUrlProvider implements ShippingCallbackUrlProviderInterface
{
    private const REQUIRED_SCHEME = 'https';

    public function __construct(
        private UrlGeneratorInterface $router,
        private LoggerInterface $logger,
        private string $route = 'sylius_paypal_order_shipping_callback',
    ) {
    }

    public function provide(): ?string
    {
        $callbackUrl = $this->router->generate($this->route, [], UrlGeneratorInterface::ABSOLUTE_URL);

        if (!str_starts_with($callbackUrl, self::REQUIRED_SCHEME . '://')) {
            $this->logger->warning(sprintf(
                'The PayPal shipping callback URL "%s" is not https, so PayPal will not ask the shop to recalculate shipping and taxes in the wallet.',
                $callbackUrl,
            ));

            return null;
        }

        return $callbackUrl;
    }
}
