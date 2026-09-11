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

    public const SYLIUS_MERCHANT_ID = 'sylius_merchant_id';

    public const PARTNER_ATTRIBUTION_ID = 'partner_attribution_id';

    public const USE_AUTHORIZE = 'use_authorize';

    public const ONBOARDING_ID = 'onboarding_id';

    public const WEBHOOK_ID = 'webhook_id';

    public const REPORTS_SFTP_USERNAME = 'reports_sftp_username';

    public const REPORTS_SFTP_PASSWORD = 'reports_sftp_password';

    public const PAY_LATER_ENABLED = 'pay_later_enabled';

    public const MESSAGING_ENABLED = 'messaging_enabled';

    public const VENMO_ENABLED = 'venmo_enabled';

    public const GOOGLE_PAY_ENABLED = 'google_pay_enabled';

    public const APPLE_PAY_ENABLED = 'apple_pay_enabled';

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

    public function syliusMerchantId(): string
    {
        return $this->required(self::SYLIUS_MERCHANT_ID);
    }

    public function hasSyliusMerchantId(): bool
    {
        return isset($this->config[self::SYLIUS_MERCHANT_ID]);
    }

    public function partnerAttributionId(): string
    {
        return $this->required(self::PARTNER_ATTRIBUTION_ID);
    }

    public function hasPartnerAttributionId(): bool
    {
        return isset($this->config[self::PARTNER_ATTRIBUTION_ID]);
    }

    public function reportsSftpUsername(): ?string
    {
        return $this->optional(self::REPORTS_SFTP_USERNAME);
    }

    public function reportsSftpPassword(): ?string
    {
        return $this->optional(self::REPORTS_SFTP_PASSWORD);
    }

    public function webhookId(): ?string
    {
        return $this->optional(self::WEBHOOK_ID);
    }

    public function isPayLaterEnabled(): bool
    {
        return $this->flag(self::PAY_LATER_ENABLED, true);
    }

    public function isMessagingEnabled(): bool
    {
        return $this->flag(self::MESSAGING_ENABLED, true);
    }

    public function isGooglePayEnabled(): bool
    {
        return $this->flag(self::GOOGLE_PAY_ENABLED, false);
    }

    public function isApplePayEnabled(): bool
    {
        return $this->flag(self::APPLE_PAY_ENABLED, false);
    }

    public function isRedirectPaymentSourceEnabled(RedirectPaymentSource $paymentSource): bool
    {
        return $this->flag($paymentSource->configurationKey(), false);
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
