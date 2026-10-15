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

namespace Tests\Sylius\PayPalPlugin\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Provider\ActiveModeProviderInterface;
use Sylius\PayPalPlugin\Provider\HostProvider;

final class HostProviderTest extends TestCase
{
    #[Test]
    public function it_provides_sandbox_hosts_when_the_active_mode_is_sandbox(): void
    {
        $hostProvider = new HostProvider($this->activeModeProvider(true));

        self::assertSame('https://api.sandbox.paypal.com/', $hostProvider->getApiBaseUrl());
        self::assertSame('reports.sandbox.paypal.com', $hostProvider->getReportsSftpHost());
        self::assertSame('https://www.sandbox.paypal.com', $hostProvider->getWebUrl());
    }

    #[Test]
    public function it_provides_production_hosts_when_the_active_mode_is_production(): void
    {
        $hostProvider = new HostProvider($this->activeModeProvider(false));

        self::assertSame('https://api.paypal.com/', $hostProvider->getApiBaseUrl());
        self::assertSame('reports.paypal.com', $hostProvider->getReportsSftpHost());
        self::assertSame('https://www.paypal.com', $hostProvider->getWebUrl());
    }

    #[Test]
    public function it_provides_hosts_for_a_given_mode_regardless_of_the_active_one(): void
    {
        $activeModeProvider = $this->createMock(ActiveModeProviderInterface::class);
        $activeModeProvider->expects(self::never())->method('isSandbox');

        $hostProvider = new HostProvider($activeModeProvider);

        self::assertSame('https://api.sandbox.paypal.com/', $hostProvider->getApiBaseUrlForMode(true));
        self::assertSame('https://api.paypal.com/', $hostProvider->getApiBaseUrlForMode(false));
        self::assertSame('reports.sandbox.paypal.com', $hostProvider->getReportsSftpHostForMode(true));
        self::assertSame('reports.paypal.com', $hostProvider->getReportsSftpHostForMode(false));
        self::assertSame('https://www.sandbox.paypal.com', $hostProvider->getWebUrlForMode(true));
        self::assertSame('https://www.paypal.com', $hostProvider->getWebUrlForMode(false));
    }

    private function activeModeProvider(bool $sandbox): ActiveModeProviderInterface
    {
        $activeModeProvider = $this->createMock(ActiveModeProviderInterface::class);
        $activeModeProvider->method('isSandbox')->willReturn($sandbox);

        return $activeModeProvider;
    }
}
