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

use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;

final class DummyOrderDetailsApi implements OrderDetailsApiInterface
{
    public static string $captureStatus = 'COMPLETED';

    public static ?\Throwable $failWith = null;

    /** @var array<string, mixed>|null */
    private ?array $nextResponse = null;

    /** @param array<string, mixed> $response */
    public function useResponse(array $response): void
    {
        $this->nextResponse = $response;
    }

    public function get(string $token, string $orderId): array
    {
        if (null !== self::$failWith) {
            throw self::$failWith;
        }

        return $this->nextResponse ?? [
            'status' => 'COMPLETED',
            'purchase_units' => [
                [
                    'payments' => [
                        'captures' => [
                            [
                                'id' => '123123',
                                'status' => self::$captureStatus,
                                'amount' => ['currency_code' => 'USD', 'value' => '0.20'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
