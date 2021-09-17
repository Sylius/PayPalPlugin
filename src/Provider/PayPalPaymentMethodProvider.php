<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodNotFoundException;

final class PayPalPaymentMethodProvider implements PayPalPaymentMethodProviderInterface
{
    private PaymentMethodRepositoryInterface $paymentMethodRepository;

    public function __construct(PaymentMethodRepositoryInterface $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function provide(): PaymentMethodInterface
    {
        $paymentMethods = $this->paymentMethodRepository->findAll();

        /** @var PaymentMethodInterface $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $paymentMethod->getGatewayConfig();

            if ($gatewayConfig->getFactoryName() === 'sylius.pay_pal') {
                return $paymentMethod;
            }
        }

        throw new PayPalPaymentMethodNotFoundException();
    }
}
