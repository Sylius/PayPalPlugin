<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class IdentityApi implements IdentityApiInterface
{
    private PayPalClientInterface $client;

    public function __construct(PayPalClientInterface $client)
    {
        $this->client = $client;
    }

    public function generateToken(string $token): string
    {
        $content = $this->client->post('v1/identity/generate-token', $token);

        return (string) $content['client_token'];
    }
}
