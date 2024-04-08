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

use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PayPalExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('sylius_is_pay_pal_enabled', [$this, 'isPayPalEnabled']),
        ];
    }

    public function isPayPalEnabled(iterable $paymentMethods): bool
    {
        /** @var PaymentMethodInterface $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $paymentMethod->getGatewayConfig();
            if ($gatewayConfig->getFactoryName() === 'sylius.pay_pal') {
                return true;
            }
        }

        return false;
    }
}
