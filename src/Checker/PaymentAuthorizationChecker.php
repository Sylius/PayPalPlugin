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

final readonly class PaymentAuthorizationChecker implements PaymentAuthorizationCheckerInterface
{
    /** @param list<string> $authorizablePaymentSources */
    public function __construct(private array $authorizablePaymentSources)
    {
    }

    public function isAuthorizeEnabled(PaymentMethodInterface $paymentMethod): bool
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (!$gatewayConfig instanceof GatewayConfigInterface) {
            return false;
        }

        if ($gatewayConfig instanceof PayumGatewayConfigInterface && $gatewayConfig->getUsePayum()) {
            return false;
        }

        return PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isAuthorizeEnabled();
    }

    public function isAuthorizable(string $paymentSource, PaymentMethodInterface $paymentMethod): bool
    {
        return $this->isAuthorizeEnabled($paymentMethod) && $this->isAuthorizablePaymentSource($paymentSource);
    }

    public function isAllowed(string $paymentSource, PaymentMethodInterface $paymentMethod): bool
    {
        return !$this->isAuthorizeEnabled($paymentMethod) || $this->isAuthorizablePaymentSource($paymentSource);
    }

    private function isAuthorizablePaymentSource(string $paymentSource): bool
    {
        return in_array($paymentSource, $this->authorizablePaymentSources, true);
    }
}
