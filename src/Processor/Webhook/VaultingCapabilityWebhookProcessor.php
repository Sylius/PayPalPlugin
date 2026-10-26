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
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresherInterface;
use Sylius\PayPalPlugin\Repository\Query\PayPalPaymentMethodQueryInterface;

final readonly class VaultingCapabilityWebhookProcessor implements WebhookProcessorInterface
{
    public const EVENT_TYPE = 'CUSTOMER.MERCHANT-INTEGRATION.CAPABILITY-UPDATED';

    public function __construct(
        private PayPalPaymentMethodQueryInterface $payPalPaymentMethodQuery,
        private VaultingCapabilityRefresherInterface $refresher,
        private ObjectManager $paymentMethodManager,
    ) {
    }

    public function supports(string $eventType): bool
    {
        return self::EVENT_TYPE === $eventType;
    }

    public function process(array $payload): void
    {
        $resource = $payload['resource'] ?? null;
        $merchantId = is_array($resource) ? ($resource['merchant_id'] ?? null) : null;

        $failure = null;
        foreach ($this->payPalPaymentMethodQuery->findAll() as $paymentMethod) {
            if (!$this->isRefreshable($paymentMethod->getGatewayConfig()?->getConfig() ?? [], $merchantId)) {
                continue;
            }

            try {
                $this->refresher->refresh($paymentMethod);
            } catch (\Throwable $exception) {
                $failure ??= $exception;
            }
        }

        $this->paymentMethodManager->flush();

        if (null !== $failure) {
            throw $failure;
        }
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
