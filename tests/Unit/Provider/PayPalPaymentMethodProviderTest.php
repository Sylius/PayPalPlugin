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
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodNotFoundException;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProvider;

final class PayPalPaymentMethodProviderTest extends TestCase
{
    /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface>&MockObject */
    private PaymentMethodRepositoryInterface&MockObject $paymentMethodRepository;

    private PayPalPaymentMethodProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->provider = new PayPalPaymentMethodProvider($this->paymentMethodRepository);
    }

    #[Test]
    public function it_provides_the_paypal_payment_method(): void
    {
        $paymentMethod = $this->payPalPaymentMethod();
        $this->paymentMethodRepository->method('findAll')->willReturn([$paymentMethod]);

        self::assertSame($paymentMethod, $this->provider->provide());
    }

    #[Test]
    public function it_throws_an_exception_when_there_is_no_paypal_payment_method(): void
    {
        $this->paymentMethodRepository->method('findAll')->willReturn([]);

        $this->expectException(PayPalPaymentMethodNotFoundException::class);

        $this->provider->provide();
    }

    #[Test]
    public function it_confirms_a_paypal_payment_method_exists(): void
    {
        $this->paymentMethodRepository->method('findAll')->willReturn([$this->payPalPaymentMethod()]);

        self::assertTrue($this->provider->exists());
    }

    #[Test]
    public function it_reports_that_no_paypal_payment_method_exists(): void
    {
        $this->paymentMethodRepository->method('findAll')->willReturn([]);

        self::assertFalse($this->provider->exists());
    }

    private function payPalPaymentMethod(): PaymentMethodInterface&MockObject
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        return $paymentMethod;
    }
}
