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
use Sylius\PayPalPlugin\Api\WebhookApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookAlreadyRegisteredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Sylius\PayPalPlugin\Provider\WebhookUrlProvider;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SellerWebhookRegistrar implements SellerWebhookRegistrarInterface
{
    private WebhookUrlProviderInterface $webhookUrlProvider;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private WebhookApiInterface $webhookApi,
        ?WebhookUrlProviderInterface $webhookUrlProvider = null,
    ) {
        $this->webhookUrlProvider = $webhookUrlProvider ?? new WebhookUrlProvider($urlGenerator);
    }

    public function register(PaymentMethodInterface $paymentMethod): void
    {
        $webhookUrl = $this->webhookUrlProvider->provide($paymentMethod);

        try {
            $this->webhookApi->register($paymentMethod, $webhookUrl);
        } catch (PayPalApiException $exception) {
            match ($exception->getErrorName()) {
                'WEBHOOK_URL_ALREADY_EXISTS' => throw new PayPalWebhookAlreadyRegisteredException(),
                'VALIDATION_ERROR' => throw new PayPalWebhookUrlNotValidException(),
                default => null,
            };
        }
    }
}
