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

use Sylius\PayPalPlugin\Manager\CredentialsManagerInterface;
use Sylius\PayPalPlugin\Model\Mode;
use Sylius\PayPalPlugin\Model\ModeSwitchView;

final class ModeSwitchViewFactory implements ModeSwitchViewFactoryInterface
{
    public function createFromConfig(array $config): ModeSwitchView
    {
        $currentMode = ($config[CredentialsManagerInterface::MODE_KEY] ?? false)
            ? Mode::Sandbox->value
            : Mode::Production->value;
        $activeClientId = (string) ($config['client_id'] ?? '');

        return new ModeSwitchView(
            currentMode: $currentMode,
            sandboxConfigured: $this->isConfigured($config, CredentialsManagerInterface::SANDBOX_CREDENTIALS_KEY, Mode::Sandbox->value, $currentMode, $activeClientId),
            productionConfigured: $this->isConfigured($config, CredentialsManagerInterface::PRODUCTION_CREDENTIALS_KEY, Mode::Production->value, $currentMode, $activeClientId),
            credentialsPreview: [
                Mode::Sandbox->value => $this->credentialsFor($config, CredentialsManagerInterface::SANDBOX_CREDENTIALS_KEY, Mode::Sandbox->value, $currentMode, $activeClientId),
                Mode::Production->value => $this->credentialsFor($config, CredentialsManagerInterface::PRODUCTION_CREDENTIALS_KEY, Mode::Production->value, $currentMode, $activeClientId),
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
