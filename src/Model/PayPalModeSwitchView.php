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

final readonly class PayPalModeSwitchView
{
    /**
     * @param array{
     *     sandbox: array{client_id: string, client_secret: string},
     *     production: array{client_id: string, client_secret: string}
     * } $credentialsPreview
     */
    public function __construct(
        public string $currentMode,
        public bool $sandboxConfigured,
        public bool $productionConfigured,
        public array $credentialsPreview,
    ) {
    }

    public function isSandboxMode(): bool
    {
        return PayPalMode::Sandbox->value === $this->currentMode;
    }
}
