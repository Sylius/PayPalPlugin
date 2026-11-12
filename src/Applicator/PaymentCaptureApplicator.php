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
use Sylius\PayPalPlugin\Dispatcher\CaptureAuthorizationPaymentRequestDispatcherInterface;

final readonly class PaymentCaptureApplicator implements PaymentCaptureApplicatorInterface
{
    public function __construct(private CaptureAuthorizationPaymentRequestDispatcherInterface $captureAuthorizationPaymentRequestDispatcher)
    {
    }

    public function capture(PaymentInterface $data): PaymentInterface
    {
        $paymentMethod = $data->getMethod();
        if (
            !$paymentMethod instanceof PaymentMethodInterface ||
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $paymentMethod->getGatewayConfig()?->getFactoryName()
        ) {
            throw new StateMachineTransitionFailedException('The payment was not paid with PayPal.');
        }

        if (PaymentInterface::STATE_AUTHORIZED !== $data->getState()) {
            throw new StateMachineTransitionFailedException(sprintf(
                'Only an authorized PayPal payment can be captured through PayPal, the payment is "%s".',
                (string) $data->getState(),
            ));
        }

        $this->captureAuthorizationPaymentRequestDispatcher->dispatch($data);

        return $data;
    }
}
