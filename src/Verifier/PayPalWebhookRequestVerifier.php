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

namespace Sylius\PayPalPlugin\Verifier;

use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\WebhookSignatureVerifierInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;
use Sylius\PayPalPlugin\Provider\WebhookIdProviderInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class PayPalWebhookRequestVerifier implements PayPalWebhookRequestVerifierInterface
{
    public function __construct(
        private PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
        private WebhookIdProviderInterface $webhookIdProvider,
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private WebhookSignatureVerifierInterface $webhookSignatureVerifier,
    ) {
    }

    public function verify(Request $request): bool
    {
        try {
            $paymentMethod = $this->payPalPaymentMethodProvider->provide();
            $webhookId = $this->webhookIdProvider->provide($paymentMethod);
            $token = $this->authorizeClientApi->authorize($paymentMethod);

            $verified = null !== $webhookId && $this->webhookSignatureVerifier->verify($request, $webhookId, $token);

            if (!$verified) {
                $freshWebhookId = $this->webhookIdProvider->refresh($paymentMethod);
                if (null !== $freshWebhookId && $freshWebhookId !== $webhookId) {
                    $verified = $this->webhookSignatureVerifier->verify($request, $freshWebhookId, $token);
                }
            }

            return $verified;
        } catch (\Throwable) {
            return false;
        }
    }
}
