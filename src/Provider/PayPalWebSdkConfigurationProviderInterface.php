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

use Sylius\Component\Core\Model\ChannelInterface;

interface PayPalWebSdkConfigurationProviderInterface
{
    public const DEFAULT_COMPONENTS = ['paypal-payments'];

    public function getScriptUrl(): string;

    /**
     * @param array<int, string> $components
     *
     * @return array<string, mixed>
     */
    public function getInstanceConfig(
        ChannelInterface $channel,
        string $pageType,
        array $components = self::DEFAULT_COMPONENTS,
        ?string $locale = null,
    ): array;
}
