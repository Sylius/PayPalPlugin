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

namespace Sylius\PayPalPlugin\Client;

use Sylius\PayPalPlugin\Exception\PayPalAuthorizationException;

interface PayPalClientInterface
{
    /** @throws PayPalAuthorizationException */
    public function authorize(string $clientId, string $clientSecret): array;

    public function get(string $url, string $token): array;

    public function post(string $url, string $token, array $data = null, array $extraHeaders = []): array;

    public function patch(string $url, string $token, array $data = null): array;
}
