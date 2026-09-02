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

interface PayPalCredentialsManagerInterface
{
    public const MODE_KEY = 'sandbox';

    public const SANDBOX_CREDENTIALS_KEY = 'sandbox_credentials';

    public const PRODUCTION_CREDENTIALS_KEY = 'production_credentials';

    /**
     * @var array<string>
     */
    public const MIRRORED_KEYS = [
        'client_id',
        'client_secret',
        'merchant_id',
        'sylius_merchant_id',
        'partner_attribution_id',
        'webhook_id',
        'reports_sftp_username',
        'reports_sftp_password',
    ];

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $credentials
     *
     * @return array<string, mixed>
     */
    public function store(array $config, bool $sandbox, array $credentials): array;

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function switchTo(array $config, bool $sandbox): array;

    /**
     * @param array<string, mixed> $config
     */
    public function hasCredentials(array $config, bool $sandbox): bool;
}
