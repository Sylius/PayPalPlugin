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

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresherInterface;

final readonly class VaultingCapabilityWebhookProcessor implements WebhookProcessorInterface
{
    public const EVENT_TYPE = 'CUSTOMER.MERCHANT-INTEGRATION.CAPABILITY-UPDATED';

    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository */
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private VaultingCapabilityRefresherInterface $refresher,
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

        foreach ($this->paymentMethodRepository->findAll() as $paymentMethod) {
            $gatewayConfig = $paymentMethod->getGatewayConfig();
            if (
                !$gatewayConfig instanceof GatewayConfigInterface ||
                SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $gatewayConfig->getFactoryName()
            ) {
                continue;
            }

            if (is_string($merchantId) && $merchantId !== ($gatewayConfig->getConfig()[PayPalGatewayConfig::MERCHANT_ID] ?? null)) {
                continue;
            }

            $this->refresher->refresh($paymentMethod);
        }
    }
}
