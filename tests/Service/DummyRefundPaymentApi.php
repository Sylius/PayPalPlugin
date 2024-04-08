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

namespace Tests\Sylius\PayPalPlugin\Service;

use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;

final class DummyRefundPaymentApi implements RefundPaymentApiInterface
{
    public function refund(
        string $token,
        string $paymentId,
        string $payPalAuthAssertion,
        string $invoiceNumber,
        string $amount,
        string $currencyCode,
    ): array {
        return ['status' => 'COMPLETED', 'id' => $paymentId];
    }
}
