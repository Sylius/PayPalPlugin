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
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\GenericApiInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class WebhookIdProvider implements WebhookIdProviderInterface
{
    public function __construct(
        private GenericApiInterface $genericApi,
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private UrlGeneratorInterface $urlGenerator,
        private PayPalHostProviderInterface $hostProvider,
        private string $webhookBaseUrl = '',
    ) {
    }

    public function provide(PaymentMethodInterface $paymentMethod): ?string
    {
        $token = $this->authorizeClientApi->authorize($paymentMethod);

        $webhookUrl = $this->webhookUrl();

        $data = $this->genericApi->get($token, $this->hostProvider->getApiBaseUrl() . 'v1/notifications/webhooks');

        /** @var array<array{id?: string, url?: string}> $webhooks */
        $webhooks = $data['webhooks'] ?? [];
        foreach ($webhooks as $webhook) {
            if (isset($webhook['url'], $webhook['id']) && $this->urlsMatch($webhook['url'], $webhookUrl)) {
                return (string) $webhook['id'];
            }
        }

        return null;
    }

    public function refresh(PaymentMethodInterface $paymentMethod): ?string
    {
        return $this->provide($paymentMethod);
    }

    private function webhookUrl(): string
    {
        if ('' !== $this->webhookBaseUrl) {
            return rtrim($this->webhookBaseUrl, '/') . $this->urlGenerator->generate(
                'sylius_paypal_webhook_refund_order',
                [],
                UrlGeneratorInterface::ABSOLUTE_PATH,
            );
        }

        return $this->urlGenerator->generate(
            'sylius_paypal_webhook_refund_order',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    private function urlsMatch(string $registered, string $expected): bool
    {
        return $this->domainsMatch($registered, $expected) &&
            $this->endpointsMatch($registered, $expected);
    }

    private function domainsMatch(string $registered, string $expected): bool
    {
        $registeredHost = strtolower((string) parse_url($registered, \PHP_URL_HOST));
        $expectedHost = strtolower((string) parse_url($expected, \PHP_URL_HOST));

        return '' !== $registeredHost && $registeredHost === $expectedHost;
    }

    private function endpointsMatch(string $registered, string $expected): bool
    {
        $registeredPath = rtrim((string) parse_url($registered, \PHP_URL_PATH), '/');
        $expectedPath = rtrim((string) parse_url($expected, \PHP_URL_PATH), '/');

        return '' !== $registeredPath && str_contains($expectedPath, $registeredPath);
    }
}
