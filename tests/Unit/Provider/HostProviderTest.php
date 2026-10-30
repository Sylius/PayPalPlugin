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
use Sylius\PayPalPlugin\Provider\HostProvider;

final class HostProviderTest extends TestCase
{
    #[Test]
    public function it_provides_the_hosts_of_the_given_mode(): void
    {
        $hostProvider = new HostProvider();

        self::assertSame('https://api.sandbox.paypal.com/', $hostProvider->getApiBaseUrlForMode(true));
        self::assertSame('https://api.paypal.com/', $hostProvider->getApiBaseUrlForMode(false));
        self::assertSame('reports.sandbox.paypal.com', $hostProvider->getReportsSftpHostForMode(true));
        self::assertSame('reports.paypal.com', $hostProvider->getReportsSftpHostForMode(false));
        self::assertSame('https://www.sandbox.paypal.com', $hostProvider->getWebUrlForMode(true));
        self::assertSame('https://www.paypal.com', $hostProvider->getWebUrlForMode(false));
    }
}
