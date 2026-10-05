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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodNotFoundException;
use Sylius\PayPalPlugin\Repository\Query\PayPalPaymentMethodQueryInterface;

final readonly class PayPalPaymentMethodProvider implements PayPalPaymentMethodProviderInterface
{
    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository */
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private ?PayPalPaymentMethodQueryInterface $payPalPaymentMethodQuery = null,
    ) {
        if (null === $this->payPalPaymentMethodQuery) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $payPalPaymentMethodQuery to "%s" constructor is deprecated and will be prohibited in 3.0',
                self::class,
            );
        }
    }

    public function provide(): PaymentMethodInterface
    {
        if (null !== $this->payPalPaymentMethodQuery) {
            return $this->payPalPaymentMethodQuery->findOne() ?? throw new PayPalPaymentMethodNotFoundException();
        }

        $paymentMethods = $this->paymentMethodRepository->findAll();

        /** @var PaymentMethodInterface $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $paymentMethod->getGatewayConfig();

            if ($gatewayConfig->getFactoryName() === SyliusPayPalExtension::PAYPAL_FACTORY_NAME) {
                return $paymentMethod;
            }
        }

        throw new PayPalPaymentMethodNotFoundException();
    }

    public function exists(): bool
    {
        if (null !== $this->payPalPaymentMethodQuery) {
            return $this->payPalPaymentMethodQuery->exists();
        }

        try {
            $this->provide();
        } catch (PayPalPaymentMethodNotFoundException) {
            return false;
        }

        return true;
    }
}
