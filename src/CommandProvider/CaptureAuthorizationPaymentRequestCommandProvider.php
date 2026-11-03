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

namespace Sylius\PayPalPlugin\CommandProvider;

use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\Command\CaptureAuthorizationPaymentRequest;

final class CaptureAuthorizationPaymentRequestCommandProvider implements PaymentRequestCommandProviderInterface
{
    public const ACTION = 'capture_authorization';

    public function supports(PaymentRequestInterface $paymentRequest): bool
    {
        return self::ACTION === $paymentRequest->getAction();
    }

    public function provide(PaymentRequestInterface $paymentRequest): object
    {
        return new CaptureAuthorizationPaymentRequest($paymentRequest->getId());
    }
}
