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

namespace Sylius\PayPalPlugin\Exception;

final class PayPalApiErrorException extends \Exception
{
    /** @param array<string, mixed> $response */
    public function __construct(string $request, array $response)
    {
        parent::__construct(sprintf('PayPal rejected the "%s" request: %s', $request, self::describe($response)));
    }

    /** @param array<string, mixed> $response */
    private static function describe(array $response): string
    {
        if ([] === $response) {
            return 'the response could not be read';
        }

        $parts = [(string) ($response['name'] ?? $response['error'] ?? 'unknown error')];

        if (isset($response['message']) || isset($response['error_description'])) {
            $parts[] = (string) ($response['message'] ?? $response['error_description']);
        }

        if (isset($response['debug_id'])) {
            $parts[] = sprintf('(debug id: %s)', $response['debug_id']);
        }

        return implode(' ', $parts);
    }
}
