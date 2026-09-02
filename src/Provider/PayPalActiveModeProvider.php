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

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodNotFoundException;
use Sylius\PayPalPlugin\Manager\PayPalCredentialsManagerInterface;

final readonly class PayPalActiveModeProvider implements PayPalActiveModeProviderInterface
{
    public function __construct(private PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider)
    {
    }

    public function isSandbox(): bool
    {
        try {
            $paymentMethod = $this->payPalPaymentMethodProvider->provide();
        } catch (PayPalPaymentMethodNotFoundException) {
            return false;
        }

        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return (bool) ($gatewayConfig->getConfig()[PayPalCredentialsManagerInterface::MODE_KEY] ?? false);
    }
}
