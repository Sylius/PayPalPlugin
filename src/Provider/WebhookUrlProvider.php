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

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class WebhookUrlProvider implements WebhookUrlProviderInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private string $webhookBaseUrl = '',
    ) {
    }

    public function provide(PaymentMethodInterface $paymentMethod): string
    {
        return $this->url(self::ROUTE, ['code' => (string) $paymentMethod->getCode()]);
    }

    public function provideLegacy(): string
    {
        return $this->url(self::LEGACY_ROUTE, []);
    }

    /** @param array<string, string> $parameters */
    private function url(string $route, array $parameters): string
    {
        if ('' === $this->webhookBaseUrl) {
            return $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return rtrim($this->webhookBaseUrl, '/') .
            $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
