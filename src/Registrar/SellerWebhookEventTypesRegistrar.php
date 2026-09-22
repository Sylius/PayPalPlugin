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
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\UpdateWebhookApiInterface;
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Exception\PayPalWebhookNotRegisteredException;
use Sylius\PayPalPlugin\Provider\WebhookIdProviderInterface;

final readonly class SellerWebhookEventTypesRegistrar implements SellerWebhookEventTypesRegistrarInterface
{
    public function __construct(
        private WebhookIdProviderInterface $webhookIdProvider,
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private UpdateWebhookApiInterface $updateWebhookApi,
    ) {
    }

    public function register(PaymentMethodInterface $paymentMethod): void
    {
        $webhookId = $this->webhookIdProvider->refresh($paymentMethod);
        if (null === $webhookId) {
            throw new PayPalWebhookNotRegisteredException((string) $paymentMethod->getCode());
        }

        $this->updateWebhookApi->updateEventTypes(
            $this->authorizeClientApi->authorize($paymentMethod),
            $webhookId,
            WebhookApi::EVENT_TYPES,
        );
    }
}
