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

use Sylius\PayPalPlugin\PackageTracking\Api\AddTrackingApiInterface;

final class DummyAddTrackingApi implements AddTrackingApiInterface
{
    /** @var array<string, mixed> */
    public static array $response = ['id' => 'PAYPAL_ORDER_ID', 'purchase_units' => []];

    /** @var array<int, array<string, mixed>> */
    public static array $requests = [];

    public static function reset(): void
    {
        self::$response = ['id' => 'PAYPAL_ORDER_ID', 'purchase_units' => []];
        self::$requests = [];
    }

    public function add(string $token, string $orderId, array $body): array
    {
        self::$requests[] = ['orderId' => $orderId, 'body' => $body];

        return self::$response;
    }
}
