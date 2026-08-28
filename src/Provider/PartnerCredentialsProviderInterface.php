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

namespace Sylius\PayPalPlugin\Provider;

use JsonException;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Model\PartnerCredentials;

interface PartnerCredentialsProviderInterface
{
    /**
     * @throws ClientExceptionInterface
     * @throws PayPalPluginException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function provide(): PartnerCredentials;
}
