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

namespace Sylius\PayPalPlugin\Updater;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\WebhookEventTypes;
use Sylius\PayPalPlugin\Model\WebhookRegistration;

final readonly class WebhookIdUpdater implements WebhookIdUpdaterInterface
{
    public function __construct(private ObjectManager $paymentMethodManager)
    {
    }

    public function update(PaymentMethodInterface $paymentMethod, string $webhookId): void
    {
        if ($webhookId === ($paymentMethod->getGatewayConfig()?->getConfig()[PayPalGatewayConfig::WEBHOOK_ID] ?? null)) {
            return;
        }

        $this->store($paymentMethod, [PayPalGatewayConfig::WEBHOOK_ID => $webhookId]);
    }

    public function updateRegistered(PaymentMethodInterface $paymentMethod, WebhookRegistration $registration): void
    {
        $this->store($paymentMethod, [
            PayPalGatewayConfig::WEBHOOK_ID => $registration->id(),
            PayPalGatewayConfig::WEBHOOK_URL => $registration->url(),
            PayPalGatewayConfig::WEBHOOK_EVENT_TYPES => WebhookEventTypes::HANDLED,
        ]);
    }

    /** @param array<string, mixed> $webhook */
    private function store(PaymentMethodInterface $paymentMethod, array $webhook): void
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (null === $gatewayConfig) {
            return;
        }

        $config = $gatewayConfig->getConfig();
        unset($config[PayPalGatewayConfig::WEBHOOK_URL], $config[PayPalGatewayConfig::WEBHOOK_EVENT_TYPES]);
        $gatewayConfig->setConfig(array_merge($config, $webhook));
        $this->paymentMethodManager->flush();
    }
}
