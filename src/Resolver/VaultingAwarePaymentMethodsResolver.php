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

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;
use Sylius\PayPalPlugin\Provider\PayPalVaultRequestProviderInterface;

final readonly class VaultingAwarePaymentMethodsResolver implements PaymentMethodsResolverInterface
{
    public function __construct(
        private PaymentMethodsResolverInterface $decoratedResolver,
        private PayPalVaultRequestProviderInterface $vaultRequestProvider,
    ) {
    }

    public function getSupportedMethods(BasePaymentInterface $subject): array
    {
        $paymentMethods = $this->decoratedResolver->getSupportedMethods($subject);

        $order = $subject instanceof PaymentInterface ? $subject->getOrder() : null;
        if (!$order instanceof OrderInterface) {
            return $paymentMethods;
        }

        return array_values(array_filter(
            $paymentMethods,
            fn (PaymentMethodInterface $paymentMethod): bool => $this->vaultRequestProvider->isVaultingRequirementMet($order, $paymentMethod),
        ));
    }

    public function supports(BasePaymentInterface $subject): bool
    {
        return $this->decoratedResolver->supports($subject);
    }
}
