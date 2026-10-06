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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodNotFoundException;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProvider;
use Sylius\PayPalPlugin\Repository\Query\PayPalPaymentMethodQueryInterface;

final class PayPalPaymentMethodProviderTest extends TestCase
{
    private PayPalPaymentMethodQueryInterface&MockObject $payPalPaymentMethodQuery;

    private PayPalPaymentMethodProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payPalPaymentMethodQuery = $this->createMock(PayPalPaymentMethodQueryInterface::class);
        $this->provider = new PayPalPaymentMethodProvider($this->payPalPaymentMethodQuery);
    }

    #[Test]
    public function it_provides_the_paypal_payment_method(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->payPalPaymentMethodQuery->method('findOne')->willReturn($paymentMethod);

        self::assertSame($paymentMethod, $this->provider->provide());
    }

    #[Test]
    public function it_throws_an_exception_when_there_is_no_paypal_payment_method(): void
    {
        $this->payPalPaymentMethodQuery->method('findOne')->willReturn(null);

        $this->expectException(PayPalPaymentMethodNotFoundException::class);

        $this->provider->provide();
    }

    #[Test]
    public function it_confirms_a_paypal_payment_method_exists(): void
    {
        $this->payPalPaymentMethodQuery->expects(self::once())->method('exists')->willReturn(true);
        $this->payPalPaymentMethodQuery->expects(self::never())->method('findOne');

        self::assertTrue($this->provider->exists());
    }

    #[Test]
    public function it_reports_that_no_paypal_payment_method_exists(): void
    {
        $this->payPalPaymentMethodQuery->method('exists')->willReturn(false);

        self::assertFalse($this->provider->exists());
    }
}
