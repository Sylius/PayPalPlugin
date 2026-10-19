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

namespace Sylius\PayPalPlugin\Checker;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface as PayumGatewayConfigInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;

final readonly class PaymentAuthorizationChecker implements PaymentAuthorizationCheckerInterface
{
    public const AUTHORIZABLE_PAYMENT_SOURCES = [
        PayPalPaymentSourceProviderInterface::PAYPAL,
        PayPalPaymentSourceProviderInterface::VENMO,
        PayPalPaymentSourceProviderInterface::CARD,
        PayPalPaymentSourceProviderInterface::APPLE_PAY,
        PayPalPaymentSourceProviderInterface::GOOGLE_PAY,
    ];

    public function authorizesPayments(PaymentMethodInterface $paymentMethod): bool
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (!$gatewayConfig instanceof GatewayConfigInterface) {
            return false;
        }

        if ($gatewayConfig instanceof PayumGatewayConfigInterface && $gatewayConfig->getUsePayum()) {
            return false;
        }

        return PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->authorizesPayments();
    }

    public function authorizes(PaymentMethodInterface $paymentMethod, string $paymentSource): bool
    {
        return
            in_array($paymentSource, self::AUTHORIZABLE_PAYMENT_SOURCES, true) &&
            $this->authorizesPayments($paymentMethod)
        ;
    }
}
