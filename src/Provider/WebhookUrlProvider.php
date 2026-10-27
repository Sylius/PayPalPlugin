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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class WebhookUrlProvider implements WebhookUrlProviderInterface
{
    private const LOCALHOST = 'localhost';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
        private string $webhookBaseUrl = '',
    ) {
    }

    public function provide(PaymentMethodInterface $paymentMethod): string
    {
        return $this->notifyUrl($paymentMethod, $this->baseUrls($paymentMethod)[0]);
    }

    public function provideCandidates(PaymentMethodInterface $paymentMethod): array
    {
        $urls = [];
        foreach ($this->baseUrls($paymentMethod) as $baseUrl) {
            $urls[] = $this->notifyUrl($paymentMethod, $baseUrl);
            $urls[] = $this->legacyUrl($baseUrl);
        }

        return array_values(array_unique($urls));
    }

    /** @return non-empty-list<string|null> the base URLs to try, null standing for the request context */
    private function baseUrls(PaymentMethodInterface $paymentMethod): array
    {
        $channelBaseUrls = $this->channelBaseUrls($paymentMethod);
        $baseUrls = null !== $this->requestStack->getMainRequest()
            ? [null, ...$channelBaseUrls]
            : [...$channelBaseUrls, null];

        return '' !== $this->webhookBaseUrl ? [$this->webhookBaseUrl, ...$baseUrls] : $baseUrls;
    }

    /** @return list<string> */
    private function channelBaseUrls(PaymentMethodInterface $paymentMethod): array
    {
        $baseUrls = [];
        foreach ($paymentMethod->getChannels() as $channel) {
            $hostname = $channel->getHostname();
            if (!$channel->isEnabled() || null === $hostname || '' === $hostname || self::LOCALHOST === strtolower($hostname)) {
                continue;
            }

            $baseUrls[] = sprintf('https://%s', $hostname);
        }

        return $baseUrls;
    }

    private function notifyUrl(PaymentMethodInterface $paymentMethod, ?string $baseUrl): string
    {
        $parameters = ['code' => (string) $paymentMethod->getCode()];
        if (null === $baseUrl) {
            return $this->urlGenerator->generate(self::ROUTE, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return rtrim($baseUrl, '/') .
            $this->urlGenerator->generate(self::ROUTE, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    private function legacyUrl(?string $baseUrl): string
    {
        if (null !== $baseUrl) {
            return rtrim($baseUrl, '/') . self::LEGACY_PATH;
        }

        $context = $this->urlGenerator->getContext();
        $port = 'https' === $context->getScheme() ? $context->getHttpsPort() : $context->getHttpPort();
        $defaultPort = 'https' === $context->getScheme() ? 443 : 80;

        return sprintf(
            '%s://%s%s%s%s',
            $context->getScheme(),
            $context->getHost(),
            $defaultPort === $port ? '' : ':' . $port,
            $context->getBaseUrl(),
            self::LEGACY_PATH,
        );
    }
}
