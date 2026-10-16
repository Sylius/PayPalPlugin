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

namespace Sylius\PayPalPlugin\Api;

use Sylius\Component\Core\Model\PaymentMethodInterface;

interface RefundPaymentApiInterface
{
    public function refund(
        PaymentMethodInterface $paymentMethod,
        string $paymentId,
        string $payPalAuthAssertion,
        string $invoiceNumber,
        string $amount,
        string $currencyCode,
        ?string $requestId = null,
    ): array;
}
