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

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PayPalPaymentPageContextProvider implements PayPalPaymentPageContextProviderInterface
{
    public function __construct(
        private PaymentSdkConfigurationProviderInterface $paymentSdkConfigurationProvider,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function provide(PaymentRequestInterface $paymentRequest, string $locale): array
    {
        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $paymentRequest->getMethod();
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        return array_merge($this->paymentSdkConfigurationProvider->provide($payment, $paymentMethod, $locale), [
            'billingAddress' => $order->getBillingAddress(),
            'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_payment_error'),
            'order' => $order,
            'payment' => $payment,
        ]);
    }
}
