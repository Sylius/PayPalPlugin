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

namespace Sylius\PayPalPlugin\Processor\Webhook;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresherInterface;

final readonly class VaultingCapabilityWebhookProcessor implements VaultingCapabilityWebhookProcessorInterface
{
    public function __construct(
        private VaultingCapabilityRefresherInterface $refresher,
        private ObjectManager $paymentMethodManager,
    ) {
    }

    public function process(array $resource, PaymentMethodInterface $paymentMethod): void
    {
        if (!$this->isRefreshable($paymentMethod->getGatewayConfig()?->getConfig() ?? [], $resource['merchant_id'] ?? null)) {
            return;
        }

        $this->refresher->refresh($paymentMethod);
        $this->paymentMethodManager->flush();
    }

    /** @param array<string, mixed> $config */
    private function isRefreshable(array $config, mixed $merchantId): bool
    {
        if (!isset($config[PayPalGatewayConfig::CLIENT_ID], $config[PayPalGatewayConfig::CLIENT_SECRET], $config[PayPalGatewayConfig::MERCHANT_ID])) {
            return false;
        }

        return !is_string($merchantId) || $config[PayPalGatewayConfig::MERCHANT_ID] === $merchantId;
    }
}
