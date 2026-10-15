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
use Sylius\PayPalPlugin\Provider\WebhookIdProviderInterface;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;

final readonly class SellerWebhookEventTypesRegistrar implements SellerWebhookEventTypesRegistrarInterface
{
    public function __construct(
        private WebhookIdProviderInterface $webhookIdProvider,
        private UpdateWebhookApiInterface $updateWebhookApi,
        private WebhookApiInterface $webhookApi,
        private WebhookUrlProviderInterface $webhookUrlProvider,
    ) {
    }

    public function register(PaymentMethodInterface $paymentMethod): void
    {
        $webhookId = $this->webhookIdProvider->refresh($paymentMethod);

        if (null !== $webhookId) {
            $this->updateWebhookApi->updateEventTypes($paymentMethod, $webhookId, WebhookApi::EVENT_TYPES);

            return;
        }

        $webhookUrl = $this->webhookUrlProvider->provide();

        try {
            $response = $this->webhookApi->register($paymentMethod, $webhookUrl);
        } catch (PayPalApiException $exception) {
            throw new PayPalWebhookNotRegisteredException((string) $paymentMethod->getCode(), $webhookUrl, $exception->getDescription());
        }

        if (!isset($response['id'])) {
            throw new PayPalWebhookNotRegisteredException((string) $paymentMethod->getCode(), $webhookUrl, 'PayPal answered without a webhook id');
        }
    }
}
