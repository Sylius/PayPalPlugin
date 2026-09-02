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

final readonly class PayPalHostProvider implements PayPalHostProviderInterface
{
    public function __construct(private ?PayPalActiveModeProviderInterface $activeModeProvider = null)
    {
    }

    public function getApiBaseUrl(): string
    {
        return $this->getApiBaseUrlForMode($this->activeModeProvider?->isSandbox() ?? false);
    }

    public function getReportsSftpHost(): string
    {
        return $this->getReportsSftpHostForMode($this->activeModeProvider?->isSandbox() ?? false);
    }

    public function getApiBaseUrlForMode(bool $sandbox): string
    {
        return $sandbox ? self::SANDBOX_API_BASE_URL : self::PRODUCTION_API_BASE_URL;
    }

    public function getReportsSftpHostForMode(bool $sandbox): string
    {
        return $sandbox ? self::SANDBOX_REPORTS_SFTP_HOST : self::PRODUCTION_REPORTS_SFTP_HOST;
    }
}
