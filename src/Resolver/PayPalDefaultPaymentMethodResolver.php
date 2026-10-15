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

namespace Sylius\PayPalPlugin\Resolver;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface as CorePaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Payment\Exception\UnresolvedDefaultPaymentMethodException;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Resolver\DefaultPaymentMethodResolverInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Webmozart\Assert\Assert;

trigger_deprecation(
    'sylius/paypal-plugin',
    '1.7',
    'The "%s" class is deprecated and will be removed in Sylius/PayPalPlugin 3.0.',
    PayPalDefaultPaymentMethodResolver::class,
);

/** @deprecated since Sylius/PayPalPlugin 1.7 and will be removed in Sylius/PayPalPlugin 3.0. */
final readonly class PayPalDefaultPaymentMethodResolver implements DefaultPaymentMethodResolverInterface
{
    /** @param PaymentMethodRepositoryInterface<CorePaymentMethodInterface> $paymentMethodRepository */
    public function __construct(
        private DefaultPaymentMethodResolverInterface $decoratedDefaultPaymentMethodResolver,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private bool $prioritizePayPal = true,
    ) {
    }

    public function getDefaultPaymentMethod(BasePaymentInterface $payment, string $prioritisedPayment = SyliusPayPalExtension::PAYPAL_FACTORY_NAME): PaymentMethodInterface
    {
        if (!$this->prioritizePayPal) {
            return $this->decoratedDefaultPaymentMethodResolver->getDefaultPaymentMethod($payment);
        }

        Assert::isInstanceOf($payment, PaymentInterface::class);

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        /** @var ChannelInterface $channel */
        $channel = $order->getChannel();

        return $this->getFirstPrioritisedPaymentForChannel($payment, $channel, $prioritisedPayment);
    }

    private function getFirstPrioritisedPaymentForChannel(PaymentInterface $payment, ChannelInterface $channel, string $prioritisedPayment): PaymentMethodInterface
    {
        $paymentMethods = $this->paymentMethodRepository->findEnabledForChannel($channel);
        if (empty($paymentMethods)) {
            throw new UnresolvedDefaultPaymentMethodException();
        }

        foreach ($paymentMethods as $paymentMethod) {
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $paymentMethod->getGatewayConfig();

            if ($gatewayConfig->getFactoryName() === $prioritisedPayment) {
                return $paymentMethod;
            }
        }

        return $this->decoratedDefaultPaymentMethodResolver->getDefaultPaymentMethod($payment);
    }
}
