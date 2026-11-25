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

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Creator\PayPalOrderCreatorInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;

final readonly class CapturePaymentResolver implements CapturePaymentResolverInterface
{
    public function __construct(private PayPalOrderCreatorInterface $payPalOrderCreator)
    {
    }

    public function resolve(PaymentInterface $payment): void
    {
        $this->payPalOrderCreator->create($payment, PayPalPaymentDetails::fromPayment($payment)->paymentSource());
    }
}
