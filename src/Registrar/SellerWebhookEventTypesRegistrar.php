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
use Sylius\PayPalPlugin\Api\UpdateWebhookApiInterface;
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Api\WebhookApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookNotRegisteredException;
use Sylius\PayPalPlugin\Model\WebhookRegistration;
use Sylius\PayPalPlugin\Provider\WebhookIdProviderInterface;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;
use Sylius\PayPalPlugin\Updater\WebhookIdUpdaterInterface;

final readonly class SellerWebhookEventTypesRegistrar implements SellerWebhookEventTypesRegistrarInterface
{
    public function __construct(
        private WebhookIdProviderInterface $webhookIdProvider,
        private UpdateWebhookApiInterface $updateWebhookApi,
        private WebhookApiInterface $webhookApi,
        private WebhookUrlProviderInterface $webhookUrlProvider,
        private WebhookIdUpdaterInterface $webhookIdUpdater,
    ) {
    }

    public function register(PaymentMethodInterface $paymentMethod): WebhookRegistration
    {
        $webhookUrl = $this->webhookUrlProvider->provide($paymentMethod);
        $webhookId = $this->webhookIdProvider->refresh($paymentMethod);

        if (null !== $webhookId) {
            $this->updateWebhookApi->update($paymentMethod, $webhookId, $webhookUrl, WebhookApi::EVENT_TYPES);
            $this->webhookIdUpdater->update($paymentMethod, $webhookId);

            return new WebhookRegistration($webhookId, $webhookUrl, false);
        }

        try {
            $response = $this->webhookApi->register($paymentMethod, $webhookUrl);
        } catch (PayPalApiException $exception) {
            throw new PayPalWebhookNotRegisteredException((string) $paymentMethod->getCode(), $webhookUrl, $exception->getDescription());
        }

        if (!isset($response['id'])) {
            throw new PayPalWebhookNotRegisteredException((string) $paymentMethod->getCode(), $webhookUrl, 'PayPal answered without a webhook id');
        }

        $this->webhookIdUpdater->update($paymentMethod, (string) $response['id']);

        return new WebhookRegistration((string) $response['id'], $webhookUrl, true);
    }
}
