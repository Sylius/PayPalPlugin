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
        return $this->url($paymentMethod, self::ROUTE, ['code' => (string) $paymentMethod->getCode()]);
    }

    public function provideLegacy(PaymentMethodInterface $paymentMethod): string
    {
        return $this->url($paymentMethod, self::LEGACY_ROUTE, []);
    }

    /** @param array<string, string> $parameters */
    private function url(PaymentMethodInterface $paymentMethod, string $route, array $parameters): string
    {
        $baseUrl = '' !== $this->webhookBaseUrl ? $this->webhookBaseUrl : $this->channelBaseUrl($paymentMethod);
        if (null === $baseUrl) {
            return $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return rtrim($baseUrl, '/') .
            $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    private function channelBaseUrl(PaymentMethodInterface $paymentMethod): ?string
    {
        foreach ($paymentMethod->getChannels() as $channel) {
            $hostname = $channel->getHostname();
            if (null !== $hostname && '' !== $hostname) {
                return sprintf('https://%s', $hostname);
            }
        }

        return null;
    }
}
