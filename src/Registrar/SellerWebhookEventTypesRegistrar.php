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

namespace Sylius\PayPalPlugin\Registrar;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\UpdateWebhookApiInterface;
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Api\WebhookApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalWebhookNotRegisteredException;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Provider\WebhookIdProviderInterface;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;

final readonly class SellerWebhookEventTypesRegistrar implements SellerWebhookEventTypesRegistrarInterface
{
    public function __construct(
        private WebhookIdProviderInterface $webhookIdProvider,
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private UpdateWebhookApiInterface $updateWebhookApi,
        private WebhookApiInterface $webhookApi,
        private WebhookUrlProviderInterface $webhookUrlProvider,
    ) {
    }

    public function register(PaymentMethodInterface $paymentMethod): void
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $sandbox = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isSandbox();

        $token = $this->authorizeClientApi->authorize($paymentMethod);
        $webhookId = $this->webhookIdProvider->refresh($paymentMethod);

        if (null !== $webhookId) {
            $this->updateWebhookApi->updateEventTypes($token, $webhookId, WebhookApi::EVENT_TYPES, $sandbox);

            return;
        }

        $webhookUrl = $this->webhookUrlProvider->provide();
        $response = $this->webhookApi->register($token, $webhookUrl, $sandbox);

        if (!isset($response['id'])) {
            throw new PayPalWebhookNotRegisteredException(
                (string) $paymentMethod->getCode(),
                $webhookUrl,
                (string) ($response['message'] ?? $response['name'] ?? 'PayPal refused the registration.'),
            );
        }
    }
}
