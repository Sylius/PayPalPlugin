<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodNotFoundException;

final class PayPalPaymentMethodProvider implements PayPalPaymentMethodProviderInterface
{
    /** @var PaymentMethodRepositoryInterface */
    private $paymentMethodRepository;

    public function __construct(PaymentMethodRepositoryInterface $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function provide(): PaymentMethodInterface
    {
        $payments = $this->paymentMethodRepository->findAll();

        /** @var PaymentMethodInterface $payment */
        foreach ($payments as $payment) {
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $payment->getGatewayConfig();

            if ($gatewayConfig->getFactoryName() === 'sylius.pay_pal') {
                return $payment;
            }
        }

        throw new PayPalPaymentMethodNotFoundException();
    }
}
