<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Service;

use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;

final class DummyRefundPaymentApi implements RefundPaymentApiInterface
{
    public function refund(string $token, string $paymentId): array
    {
        return ['status' => 'COMPLETED', 'id' => $paymentId];
    }
}
