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

namespace Sylius\PayPalPlugin\Factory;

use Sylius\PayPalPlugin\Manager\PayPalCredentialsManagerInterface;
use Sylius\PayPalPlugin\Model\PayPalMode;
use Sylius\PayPalPlugin\Model\PayPalModeSwitchView;

final class PayPalModeSwitchViewFactory implements PayPalModeSwitchViewFactoryInterface
{
    public function createFromConfig(array $config): PayPalModeSwitchView
    {
        $currentMode = ($config[PayPalCredentialsManagerInterface::MODE_KEY] ?? false)
            ? PayPalMode::Sandbox->value
            : PayPalMode::Production->value;
        $activeClientId = (string) ($config['client_id'] ?? '');

        return new PayPalModeSwitchView(
            currentMode: $currentMode,
            sandboxConfigured: $this->isConfigured($config, PayPalCredentialsManagerInterface::SANDBOX_CREDENTIALS_KEY, PayPalMode::Sandbox->value, $currentMode, $activeClientId),
            productionConfigured: $this->isConfigured($config, PayPalCredentialsManagerInterface::PRODUCTION_CREDENTIALS_KEY, PayPalMode::Production->value, $currentMode, $activeClientId),
            credentialsPreview: [
                PayPalMode::Sandbox->value => $this->credentialsFor($config, PayPalCredentialsManagerInterface::SANDBOX_CREDENTIALS_KEY, PayPalMode::Sandbox->value, $currentMode, $activeClientId),
                PayPalMode::Production->value => $this->credentialsFor($config, PayPalCredentialsManagerInterface::PRODUCTION_CREDENTIALS_KEY, PayPalMode::Production->value, $currentMode, $activeClientId),
            ],
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function isConfigured(array $config, string $vaultKey, string $mode, string $currentMode, string $activeClientId): bool
    {
        $vaultClientId = (string) ($config[$vaultKey]['client_id'] ?? '');

        return '' !== $vaultClientId || ($mode === $currentMode && '' !== $activeClientId);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{client_id: string, client_secret: string}
     */
    private function credentialsFor(array $config, string $vaultKey, string $mode, string $currentMode, string $activeClientId): array
    {
        /** @var array<string, mixed> $vault */
        $vault = $config[$vaultKey] ?? [];
        $isActiveMode = $mode === $currentMode;

        return [
            'client_id' => (string) ($vault['client_id'] ?? ($isActiveMode ? $activeClientId : '')),
            'client_secret' => (string) ($vault['client_secret'] ?? ($isActiveMode ? (string) ($config['client_secret'] ?? '') : '')),
        ];
    }
}
