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

namespace Sylius\PayPalPlugin\Model;

use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Webmozart\Assert\Assert;

final readonly class PayPalGatewayConfig
{
    public const CLIENT_ID = 'client_id';

    public const CLIENT_SECRET = 'client_secret';

    public const MERCHANT_ID = 'merchant_id';

    public const PARTNER_ATTRIBUTION_ID = 'partner_attribution_id';

    public const ONBOARDING_SCOPE = 'onboarding_scope';

    public const SANDBOX = 'sandbox';

    public const USE_AUTHORIZE = 'use_authorize';

    public const WEBHOOK_ID = 'webhook_id';

    public const WEBHOOK_URL = 'webhook_url';

    public const WEBHOOK_EVENT_TYPES = 'webhook_event_types';

    public const EXPRESS_CHECKOUT_ENABLED = 'express_checkout_enabled';

    public const PAY_LATER_ENABLED = 'pay_later_enabled';

    public const MESSAGING_ENABLED = 'messaging_enabled';

    public const VENMO_ENABLED = 'venmo_enabled';

    public const GOOGLE_PAY_ENABLED = 'google_pay_enabled';

    public const APPLE_PAY_ENABLED = 'apple_pay_enabled';

    public const CARD_THREE_D_SECURE_ALWAYS = 'card_three_d_secure_always';

    /** @param array<string, mixed> $config */
    private function __construct(private array $config)
    {
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        return new self($config);
    }

    public static function fromGatewayConfig(GatewayConfigInterface $gatewayConfig): self
    {
        return new self($gatewayConfig->getConfig());
    }

    public function clientId(): string
    {
        return $this->required(self::CLIENT_ID);
    }

    public function hasClientId(): bool
    {
        return isset($this->config[self::CLIENT_ID]);
    }

    public function clientSecret(): string
    {
        return $this->required(self::CLIENT_SECRET);
    }

    public function merchantId(): string
    {
        return $this->required(self::MERCHANT_ID);
    }

    public function partnerAttributionId(): string
    {
        return $this->required(self::PARTNER_ATTRIBUTION_ID);
    }

    public function hasPartnerAttributionId(): bool
    {
        return isset($this->config[self::PARTNER_ATTRIBUTION_ID]);
    }

    public function webhookId(): ?string
    {
        return $this->optional(self::WEBHOOK_ID);
    }

    public function webhookUrl(): ?string
    {
        return $this->optional(self::WEBHOOK_URL);
    }

    public function webhookEventTypes(): ?WebhookEventTypes
    {
        $eventTypes = $this->config[self::WEBHOOK_EVENT_TYPES] ?? null;

        return is_array($eventTypes) ? WebhookEventTypes::fromNames($eventTypes) : null;
    }

    public function isExpressCheckoutEnabled(): bool
    {
        return $this->flag(self::EXPRESS_CHECKOUT_ENABLED, true);
    }

    public function isPayLaterEnabled(): bool
    {
        return $this->flag(self::PAY_LATER_ENABLED, false);
    }

    public function isMessagingEnabled(): bool
    {
        return $this->flag(self::MESSAGING_ENABLED, false);
    }

    public function isVenmoEnabled(): bool
    {
        return $this->flag(self::VENMO_ENABLED, false);
    }

    public function isGooglePayEnabled(): bool
    {
        return $this->flag(self::GOOGLE_PAY_ENABLED, false);
    }

    public function isApplePayEnabled(): bool
    {
        return $this->flag(self::APPLE_PAY_ENABLED, false);
    }

    public function isAuthorizeEnabled(): bool
    {
        return $this->flag(self::USE_AUTHORIZE, false);
    }

    public function isSandbox(): bool
    {
        return $this->flag(self::SANDBOX, false);
    }

    public function isCardThreeDSecureAlways(): bool
    {
        return $this->flag(self::CARD_THREE_D_SECURE_ALWAYS, false);
    }

    public function isRedirectPaymentSourceEnabled(RedirectPaymentSource $paymentSource): bool
    {
        return $this->flag($paymentSource->configurationKey(), false);
    }

    public function onboardingScope(): ?OnboardingScope
    {
        $scope = $this->config[self::ONBOARDING_SCOPE] ?? null;

        return is_array($scope) ? OnboardingScope::fromArray($scope) : null;
    }

    private function required(string $key): string
    {
        Assert::keyExists($this->config, $key);

        return (string) $this->config[$key];
    }

    private function optional(string $key): ?string
    {
        return isset($this->config[$key]) ? (string) $this->config[$key] : null;
    }

    private function flag(string $key, bool $default): bool
    {
        return (bool) ($this->config[$key] ?? $default);
    }
}
