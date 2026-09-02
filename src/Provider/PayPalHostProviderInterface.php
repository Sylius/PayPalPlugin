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

namespace Sylius\PayPalPlugin\Provider;

interface PayPalHostProviderInterface
{
    public const SANDBOX_API_BASE_URL = 'https://api.sandbox.paypal.com/';

    public const PRODUCTION_API_BASE_URL = 'https://api.paypal.com/';

    public const SANDBOX_REPORTS_SFTP_HOST = 'reports.sandbox.paypal.com';

    public const PRODUCTION_REPORTS_SFTP_HOST = 'reports.paypal.com';

    public function getApiBaseUrl(): string;

    public function getReportsSftpHost(): string;

    public function getApiBaseUrlForMode(bool $sandbox): string;

    public function getReportsSftpHostForMode(bool $sandbox): string;
}
