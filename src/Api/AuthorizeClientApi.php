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

namespace Sylius\PayPalPlugin\Api;

use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Exception\PayPalAuthorizationException;
use Sylius\PayPalPlugin\Model\AccessToken;

final readonly class AuthorizeClientApi implements AuthorizeClientApiInterface
{
    public function __construct(private PayPalClientInterface $payPalClient)
    {
    }

    public function authorize(string $clientId, string $clientSecret, bool $sandbox): AccessToken
    {
        $content = $this->payPalClient->authorize($clientId, $clientSecret, $sandbox);

        if (!is_string($content['access_token'] ?? null) || !is_numeric($content['expires_in'] ?? null)) {
            throw new PayPalAuthorizationException();
        }

        return new AccessToken($content['access_token'], (int) $content['expires_in']);
    }
}
