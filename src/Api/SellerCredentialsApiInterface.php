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

use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;

interface SellerCredentialsApiInterface
{
    /**
     * @return array{client_id: string, client_secret: string, payer_id: string}
     *
     * @throws JsonException
     * @throws PayPalPluginException
     * @throws ClientExceptionInterface
     */
    public function get(string $onboardingToken, string $partnerId): array;
}
