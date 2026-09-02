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
use Sylius\PayPalPlugin\Model\OnboardingStatus;

interface MerchantOnboardingStatusApiInterface
{
    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     * @throws PayPalPluginException
     */
    public function get(string $sellerToken, string $partnerId, string $merchantId): OnboardingStatus;
}
