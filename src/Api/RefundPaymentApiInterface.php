<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

interface RefundPaymentApiInterface
{
    public function refund(string $token, string $paymentId, string $payPalAuthAssertion): array;
}
