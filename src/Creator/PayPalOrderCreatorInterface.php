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

namespace Sylius\PayPalPlugin\Creator;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;

interface PayPalOrderCreatorInterface
{
    public function create(
        PaymentInterface $payment,
        string $paymentSource,
        ?string $customId = null,
        ?string $requestId = null,
        ?string $returnUrl = null,
        ?string $cancelUrl = null,
    ): ?PayPalPaymentDetails;
}
