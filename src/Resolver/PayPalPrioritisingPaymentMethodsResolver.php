<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Resolver;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;

final class PayPalPrioritisingPaymentMethodsResolver implements PaymentMethodsResolverInterface
{
    private PaymentMethodsResolverInterface $decoratedPaymentMethodsResolver;

    private string $firstPaymentMethodFactoryName;

    public function __construct(PaymentMethodsResolverInterface $decoratedPaymentMethodsResolver, string $firstPaymentMethodFactoryName)
    {
        $this->decoratedPaymentMethodsResolver = $decoratedPaymentMethodsResolver;
        $this->firstPaymentMethodFactoryName = $firstPaymentMethodFactoryName;
    }

    public function getSupportedMethods(BasePaymentInterface $subject): array
    {
        return $this->sortPayments(
            $this->decoratedPaymentMethodsResolver->getSupportedMethods($subject),
            $this->firstPaymentMethodFactoryName
        );
    }

    public function supports(BasePaymentInterface $subject): bool
    {
        return $this->decoratedPaymentMethodsResolver->supports($subject);
    }

    /**
     * @return PaymentMethodInterface[]
     */
    private function sortPayments(array $payments, string $firstPaymentFactoryName): array
    {
        /** @var PaymentMethodInterface[] $sortedPayments */
        $sortedPayments = [];

        /** @var PaymentMethodInterface $payment */
        foreach ($payments as $payment) {
            $gatewayConfig = $payment->getGatewayConfig();

            if ($gatewayConfig !== null && $gatewayConfig->getFactoryName() === $firstPaymentFactoryName) {
                array_unshift($sortedPayments, $payment);
            } else {
                $sortedPayments[] = $payment;
            }
        }

        return $sortedPayments;
    }
}
