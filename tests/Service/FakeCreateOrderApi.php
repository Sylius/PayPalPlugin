<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Service;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;

final class FakeCreateOrderApi implements CreateOrderApiInterface
{
    public function create(string $token, PaymentInterface $payment, string $referenceId): array
    {
        return ['id' => 'PAYPAL_ORDER_ID', 'status' => 'CREATED'];
    }
}
