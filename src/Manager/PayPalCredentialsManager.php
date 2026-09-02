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

namespace Sylius\PayPalPlugin\Manager;

final class PayPalCredentialsManager implements PayPalCredentialsManagerInterface
{
    public function store(array $config, bool $sandbox, array $credentials): array
    {
        $config = $this->snapshotActiveIntoVault($config);

        $config[$this->vaultKey($sandbox)] = $this->filterCredentials($credentials);
        $config[self::MODE_KEY] = $sandbox;

        return $this->mirror($config, $credentials);
    }

    public function switchTo(array $config, bool $sandbox): array
    {
        $config = $this->snapshotActiveIntoVault($config);

        /** @var array<string, mixed> $vault */
        $vault = $config[$this->vaultKey($sandbox)] ?? [];
        $config[self::MODE_KEY] = $sandbox;

        return $this->mirror($config, $vault);
    }

    public function hasCredentials(array $config, bool $sandbox): bool
    {
        /** @var array<string, mixed> $vault */
        $vault = $config[$this->vaultKey($sandbox)] ?? [];

        return isset($vault['client_id']) && '' !== (string) $vault['client_id'];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function snapshotActiveIntoVault(array $config): array
    {
        if (!array_key_exists(self::MODE_KEY, $config)) {
            return $config;
        }

        $activeCredentials = $this->extractMirroredKeys($config);
        if ([] === $activeCredentials) {
            return $config;
        }

        $config[$this->vaultKey((bool) $config[self::MODE_KEY])] = $activeCredentials;

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $credentials
     *
     * @return array<string, mixed>
     */
    private function mirror(array $config, array $credentials): array
    {
        foreach (self::MIRRORED_KEYS as $key) {
            $config[$key] = $credentials[$key] ?? null;
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function extractMirroredKeys(array $config): array
    {
        $credentials = [];
        foreach (self::MIRRORED_KEYS as $key) {
            if (array_key_exists($key, $config) && null !== $config[$key]) {
                $credentials[$key] = $config[$key];
            }
        }

        return $credentials;
    }

    /**
     * @param array<string, mixed> $credentials
     *
     * @return array<string, mixed>
     */
    private function filterCredentials(array $credentials): array
    {
        return array_intersect_key($credentials, array_flip(self::MIRRORED_KEYS));
    }

    private function vaultKey(bool $sandbox): string
    {
        return $sandbox ? self::SANDBOX_CREDENTIALS_KEY : self::PRODUCTION_CREDENTIALS_KEY;
    }
}
