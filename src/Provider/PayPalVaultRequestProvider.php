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

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\PayPalVaultRequest;
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresherInterface;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolverInterface;
use Sylius\PayPalPlugin\Resolver\VaultRequestResolverInterface;

final readonly class PayPalVaultRequestProvider implements PayPalVaultRequestProviderInterface
{
    public function __construct(
        private VaultRequestResolverInterface $vaultRequestResolver,
        private PayPalPaymentMethodsResolverInterface $payPalMethodsResolver,
        private VaultingCapabilityRefresherInterface $vaultingCapabilityRefresher,
    ) {
    }

    public function provide(OrderInterface $order, ?PaymentMethodInterface $paymentMethod): ?PayPalVaultRequest
    {
        /** @var ChannelInterface|null $channel */
        $channel = $order->getChannel();
        if (!$this->canVault($paymentMethod, $channel)) {
            return null;
        }

        return $this->vaultRequestResolver->resolve($order);
    }

    public function provideForCart(OrderInterface $order): ?PayPalVaultRequest
    {
        return $this->provide($order, $this->cartPaymentMethod($order));
    }

    public function isVaultingEnabledInChannel(ChannelInterface $channel): bool
    {
        if (!$this->vaultRequestResolver->vaultsInChannel($channel)) {
            return false;
        }

        foreach ($this->payPalMethodsResolver->getInChannel($channel) as $paymentMethod) {
            if ($this->canVault($paymentMethod, $channel)) {
                return true;
            }
        }

        return false;
    }

    private function canVault(?PaymentMethodInterface $paymentMethod, ?ChannelInterface $channel): bool
    {
        if (null === $paymentMethod) {
            return false;
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (
            !$gatewayConfig instanceof GatewayConfigInterface ||
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $gatewayConfig->getFactoryName()
        ) {
            return false;
        }

        if (PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isVaultingEnabled()) {
            return true;
        }

        return
            null !== $channel &&
            $this->vaultRequestResolver->vaultsInChannel($channel) &&
            $this->vaultingCapabilityRefresher->refresh($paymentMethod)
        ;
    }

    private function cartPaymentMethod(OrderInterface $order): ?PaymentMethodInterface
    {
        $paymentMethod = $order->getLastPayment(BasePaymentInterface::STATE_CART)?->getMethod();
        if (SyliusPayPalExtension::PAYPAL_FACTORY_NAME === $paymentMethod?->getGatewayConfig()?->getFactoryName()) {
            return $paymentMethod;
        }

        /** @var ChannelInterface|null $channel */
        $channel = $order->getChannel();
        if (null === $channel) {
            return null;
        }

        return $this->payPalMethodsResolver->getInChannel($channel)[0] ?? null;
    }
}
