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

namespace Sylius\PayPalPlugin\Factory;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;

interface PayPalOrderFactoryInterface
{
    public function create(
        PaymentInterface $payment,
        string $referenceId,
        string $paymentSource = PayPalPaymentSourceProviderInterface::PAYPAL,
        ?string $payerActionReturnNonce = null,
        ?string $payerActionCancelNonce = null,
    ): PayPalOrder;
}
