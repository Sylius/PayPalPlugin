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

use Doctrine\Persistence\ObjectManager;
use Psr\Cache\CacheItemPoolInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;

final readonly class PersistingWebhookIdProvider implements WebhookIdProviderInterface
{
    public function __construct(
        private WebhookIdProviderInterface $webhookIdProvider,
        private ObjectManager $paymentMethodManager,
        private CacheItemPoolInterface $cache,
        private int $refreshCooldown,
    ) {
    }

    public function provide(PaymentMethodInterface $paymentMethod): ?string
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (null === $gatewayConfig) {
            return $this->webhookIdProvider->provide($paymentMethod);
        }

        $config = $gatewayConfig->getConfig();
        if (isset($config[PayPalGatewayConfig::WEBHOOK_ID]) && '' !== $config[PayPalGatewayConfig::WEBHOOK_ID]) {
            return (string) $config[PayPalGatewayConfig::WEBHOOK_ID];
        }

        $webhookId = $this->webhookIdProvider->provide($paymentMethod);
        if (null !== $webhookId) {
            $this->store($paymentMethod, $webhookId);
        }

        return $webhookId;
    }

    public function refresh(PaymentMethodInterface $paymentMethod): ?string
    {
        if (!$this->cooldownAllows($paymentMethod)) {
            return null;
        }

        $webhookId = $this->webhookIdProvider->refresh($paymentMethod);
        if (null !== $webhookId) {
            $this->store($paymentMethod, $webhookId);
        }

        return $webhookId;
    }

    private function cooldownAllows(PaymentMethodInterface $paymentMethod): bool
    {
        $item = $this->cache->getItem(
            'sylius_paypal_webhook_id_refresh_' . hash('sha256', (string) $paymentMethod->getCode()),
        );
        if ($item->isHit()) {
            return false;
        }

        $item->expiresAfter($this->refreshCooldown);
        $this->cache->save($item);

        return true;
    }

    private function store(PaymentMethodInterface $paymentMethod, string $webhookId): void
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (null === $gatewayConfig) {
            return;
        }

        $config = $gatewayConfig->getConfig();
        $config[PayPalGatewayConfig::WEBHOOK_ID] = $webhookId;
        $gatewayConfig->setConfig($config);
        $this->paymentMethodManager->flush();
    }
}
