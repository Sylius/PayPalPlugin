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

final readonly class AuthorizeClientApi implements AuthorizeClientApiInterface
{
    public function __construct(private PayPalClientInterface $payPalClient)
    {
    }

    public function authorize(string $clientId, string $clientSecret, bool $sandbox): string
    {
        $content = $this->payPalClient->authorize($clientId, $clientSecret, $sandbox);

        return (string) $content['access_token'];
    }
}
