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

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\GenericApiInterface;
use Sylius\PayPalPlugin\Api\UpdateWebhookApiInterface;
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Api\WebhookApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookNotRegisteredException;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\WebhookRegistration;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
use Sylius\PayPalPlugin\Provider\WebhookIdProviderInterface;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;
use Sylius\PayPalPlugin\Updater\WebhookIdUpdaterInterface;

final readonly class SellerWebhookEventTypesRegistrar implements SellerWebhookEventTypesRegistrarInterface
{
    public function __construct(
        private WebhookIdProviderInterface $webhookIdProvider,
        private UpdateWebhookApiInterface $updateWebhookApi,
        private WebhookApiInterface $webhookApi,
        private GenericApiInterface $genericApi,
        private HostProviderInterface $hostProvider,
        private WebhookUrlProviderInterface $webhookUrlProvider,
        private WebhookIdUpdaterInterface $webhookIdUpdater,
    ) {
    }

    public function register(PaymentMethodInterface $paymentMethod): WebhookRegistration
    {
        $webhookId = $this->webhookIdProvider->refresh($paymentMethod);

        if (null !== $webhookId) {
            $webhookUrl = $this->registeredNotifyUrl($paymentMethod, $webhookId) ?? $this->webhookUrlProvider->provide($paymentMethod);
            $registration = $this->update($paymentMethod, $webhookId, $webhookUrl);
            $this->webhookIdUpdater->update($paymentMethod, $webhookId, $webhookUrl, WebhookApi::EVENT_TYPES);

            return $registration;
        }

        $webhookUrl = $this->webhookUrlProvider->provide($paymentMethod);

        try {
            $response = $this->webhookApi->register($paymentMethod, $webhookUrl);
        } catch (PayPalApiException $exception) {
            throw new PayPalWebhookNotRegisteredException((string) $paymentMethod->getCode(), $webhookUrl, $exception->getDescription());
        }

        if (!isset($response['id'])) {
            throw new PayPalWebhookNotRegisteredException((string) $paymentMethod->getCode(), $webhookUrl, 'PayPal answered without a webhook id');
        }

        $this->webhookIdUpdater->update($paymentMethod, (string) $response['id'], $webhookUrl, WebhookApi::EVENT_TYPES);

        return WebhookRegistration::registered((string) $response['id'], $webhookUrl);
    }

    private function registeredNotifyUrl(PaymentMethodInterface $paymentMethod, string $webhookId): ?string
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $sandbox = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isSandbox();

        $webhook = $this->genericApi->get(
            $paymentMethod,
            $this->hostProvider->getApiBaseUrlForMode($sandbox) . 'v1/notifications/webhooks/' . $webhookId,
        );
        $registeredUrl = $webhook['url'] ?? null;

        return is_string($registeredUrl) && $this->webhookUrlProvider->isNotifyUrl($paymentMethod, $registeredUrl) ? $registeredUrl : null;
    }

    private function update(PaymentMethodInterface $paymentMethod, string $webhookId, string $webhookUrl): WebhookRegistration
    {
        try {
            $this->updateWebhookApi->update($paymentMethod, $webhookId, $webhookUrl, WebhookApi::EVENT_TYPES);
        } catch (PayPalApiException $exception) {
            if ('WEBHOOK_PATCH_REQUEST_NO_CHANGE' !== $exception->getErrorName()) {
                throw $exception;
            }

            return WebhookRegistration::upToDate($webhookId, $webhookUrl);
        }

        return WebhookRegistration::updated($webhookId, $webhookUrl);
    }
}
