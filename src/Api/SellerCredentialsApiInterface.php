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

interface SellerCredentialsApiInterface
{
    /**
     * @return array{client_id: string, client_secret: string, payer_id: string}
     */
    public function get(string $onboardingToken, string $partnerId): array;
}
