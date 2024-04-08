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

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;

final class FakeCreateOrderApi implements CreateOrderApiInterface
{
    public function create(string $token, PaymentInterface $payment, string $referenceId): array
    {
        return ['id' => 'PAYPAL_ORDER_ID', 'status' => 'CREATED'];
    }
}
