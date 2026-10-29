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

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\GenericApiInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;

final readonly class WebhookIdProvider implements WebhookIdProviderInterface
{
    public function __construct(
        private GenericApiInterface $genericApi,
        private HostProviderInterface $hostProvider,
        private WebhookUrlProviderInterface $webhookUrlProvider,
    ) {
    }

    public function provide(PaymentMethodInterface $paymentMethod): ?string
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $sandbox = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isSandbox();

        $data = $this->genericApi->get($paymentMethod, $this->hostProvider->getApiBaseUrlForMode($sandbox) . 'v1/notifications/webhooks');

        /** @var array<array{id?: string, url?: string}> $webhooks */
        $webhooks = $data['webhooks'] ?? [];

        return
            $this->findWebhookId($webhooks, $this->webhookUrlProvider->provide($paymentMethod)) ??
            $this->findWebhookId($webhooks, $this->webhookUrlProvider->provideLegacy($paymentMethod))
        ;
    }

    public function refresh(PaymentMethodInterface $paymentMethod): ?string
    {
        return $this->provide($paymentMethod);
    }

    /** @param array<array{id?: string, url?: string}> $webhooks */
    private function findWebhookId(array $webhooks, string $webhookUrl): ?string
    {
        foreach ($webhooks as $webhook) {
            if (isset($webhook['url'], $webhook['id']) && $this->urlsMatch($webhook['url'], $webhookUrl)) {
                return (string) $webhook['id'];
            }
        }

        return null;
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

        return '' !== $registeredPath && str_ends_with($expectedPath, $registeredPath);
    }
}
