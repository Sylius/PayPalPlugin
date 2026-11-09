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

final readonly class WebhookIdUpdater implements WebhookIdUpdaterInterface
{
    public function __construct(private ObjectManager $paymentMethodManager)
    {
    }

    public function update(PaymentMethodInterface $paymentMethod, string $webhookId, ?string $webhookUrl = null, ?array $eventTypes = null): void
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (null === $gatewayConfig) {
            return;
        }

        $config = $gatewayConfig->getConfig();
        $config[PayPalGatewayConfig::WEBHOOK_ID] = $webhookId;
        if (null === $webhookUrl || null === $eventTypes) {
            unset($config[PayPalGatewayConfig::WEBHOOK_URL], $config[PayPalGatewayConfig::WEBHOOK_EVENT_TYPES]);
        } else {
            $config[PayPalGatewayConfig::WEBHOOK_URL] = $webhookUrl;
            $config[PayPalGatewayConfig::WEBHOOK_EVENT_TYPES] = $eventTypes;
        }
        $gatewayConfig->setConfig($config);
        $this->paymentMethodManager->flush();
    }
}
