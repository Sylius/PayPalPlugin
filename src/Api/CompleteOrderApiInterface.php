<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

use Sylius\Component\Core\Model\PaymentInterface;

interface CompleteOrderApiInterface
{
    public function complete(string $token, string $orderId): array;
}
