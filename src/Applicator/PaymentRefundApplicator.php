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

namespace Sylius\PayPalPlugin\Applicator;

use Sylius\Bundle\ApiBundle\Exception\StateMachineTransitionFailedException;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Dispatcher\RefundPayPalCapturePaymentRequestDispatcherInterface;

final readonly class PaymentRefundApplicator implements PaymentRefundApplicatorInterface
{
    public function __construct(private RefundPayPalCapturePaymentRequestDispatcherInterface $refundPaymentRequestDispatcher)
    {
    }

    public function refund(PaymentInterface $data): PaymentInterface
    {
        $paymentMethod = $data->getMethod();
        if (
            PaymentInterface::STATE_COMPLETED !== $data->getState() ||
            !$paymentMethod instanceof PaymentMethodInterface ||
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $paymentMethod->getGatewayConfig()?->getFactoryName()
        ) {
            throw new StateMachineTransitionFailedException(sprintf(
                'Only a completed PayPal payment can be refunded through PayPal, the payment is "%s".',
                (string) $data->getState(),
            ));
        }

        $this->refundPaymentRequestDispatcher->dispatch($data);

        return $data;
    }
}
