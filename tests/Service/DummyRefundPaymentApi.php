<?php

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
        string $currencyCode
    ): array {
        return ['status' => 'COMPLETED', 'id' => $paymentId];
    }
}
