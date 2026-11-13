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

namespace Tests\Sylius\PayPalPlugin\Functional;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalApiMocker;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalHttpClientWithExpectations;

trait MocksPayPalApiTrait
{
    #[Before]
    public function resetPayPalApiExpectations(): void
    {
        $this->payPalHttpClient()->resetExpectations();
    }

    #[After]
    public function assertPayPalApiExpectationsWereMet(): void
    {
        $expectations = $this->payPalHttpClient()->getExpectations();
        $this->payPalHttpClient()->resetExpectations();
        self::ensureKernelShutdown();

        self::assertSame([], $expectations, 'Some expected PayPal API requests were never sent.');
    }

    private function payPalApi(): PayPalApiMocker
    {
        return self::getContainer()->get(PayPalApiMocker::class);
    }

    private function payPalHttpClient(): PayPalHttpClientWithExpectations
    {
        return self::getContainer()->get(PayPalHttpClientWithExpectations::class);
    }
}
