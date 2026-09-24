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

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PayPalWebhookUrlProvider implements PayPalWebhookUrlProviderInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private string $webhookBaseUrl = '',
    ) {
    }

    public function provide(): string
    {
        if ('' === $this->webhookBaseUrl) {
            return $this->urlGenerator->generate(self::ROUTE, [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return rtrim($this->webhookBaseUrl, '/') .
            $this->urlGenerator->generate(self::ROUTE, [], UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
