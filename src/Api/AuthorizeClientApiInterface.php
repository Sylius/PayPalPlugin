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

use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Model\AccessToken;

interface AuthorizeClientApiInterface
{
    /** @throws PayPalApiException */
    public function authorize(string $clientId, string $clientSecret, bool $sandbox): AccessToken;
}
