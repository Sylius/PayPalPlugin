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

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final readonly class IdentityApi implements IdentityApiInterface
{
    public function __construct(private PayPalClientInterface $client)
    {
    }

    public function generateToken(PaymentMethodInterface $paymentMethod): string
    {
        $content = $this->client->post($paymentMethod, 'v1/identity/generate-token');

        return (string) $content['client_token'];
    }
}
