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
use Psr\Http\Message\RequestInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;

interface PayPalOnboardingRequestExecutorInterface
{
    /**
     * @return array<string, mixed>
     *
     * @throws ClientExceptionInterface
     * @throws PayPalPluginException
     * @throws JsonException
     */
    public function execute(RequestInterface $request, string $operation): array;
}
