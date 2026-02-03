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

    private PayPalPaymentMethodProvider $payPalPaymentMethodProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->payPalPaymentMethodProvider = new PayPalPaymentMethodProvider($this->paymentMethodRepository);
    }

    #[Test]
    public function it_provides_pay_pal_payment_method(): void
    {
        $payPalPaymentMethod = $this->createMock(PaymentMethodInterface::class);
        $payPalGatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $this->paymentMethodRepository
            ->method('findBy')
            ->with(['enabled' => true])
            ->willReturn([$payPalPaymentMethod])
        ;

        $payPalPaymentMethod
            ->method('getGatewayConfig')
            ->willReturn($payPalGatewayConfig)
        ;

        $payPalGatewayConfig
            ->method('getFactoryName')
            ->willReturn('sylius_paypal')
        ;

        $result = $this->payPalPaymentMethodProvider->provide();

        self::assertSame($payPalPaymentMethod, $result);
    }

    #[Test]
    public function it_provides_first_pay_pal_payment_method_when_multiple_exist(): void
    {
        $firstPayPalPaymentMethod = $this->createMock(PaymentMethodInterface::class);
        $secondPayPalPaymentMethod = $this->createMock(PaymentMethodInterface::class);
        $firstPayPalGatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $secondPayPalGatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $this->paymentMethodRepository
            ->method('findBy')
            ->with(['enabled' => true])
            ->willReturn([$firstPayPalPaymentMethod, $secondPayPalPaymentMethod])
        ;

        $firstPayPalPaymentMethod
            ->method('getGatewayConfig')
            ->willReturn($firstPayPalGatewayConfig)
        ;

        $firstPayPalGatewayConfig
            ->method('getFactoryName')
            ->willReturn('sylius_paypal')
        ;

        $secondPayPalPaymentMethod
            ->method('getGatewayConfig')
            ->willReturn($secondPayPalGatewayConfig)
        ;

        $secondPayPalGatewayConfig
            ->method('getFactoryName')
            ->willReturn('sylius_paypal')
        ;

        $result = $this->payPalPaymentMethodProvider->provide();

        self::assertSame($firstPayPalPaymentMethod, $result);
    }

    #[Test]
    public function it_provides_pay_pal_payment_method_when_other_payment_methods_exist(): void
    {
        $otherPaymentMethod = $this->createMock(PaymentMethodInterface::class);
        $payPalPaymentMethod = $this->createMock(PaymentMethodInterface::class);
        $otherGatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $payPalGatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $this->paymentMethodRepository
            ->method('findBy')
            ->with(['enabled' => true])
            ->willReturn([$otherPaymentMethod, $payPalPaymentMethod])
        ;

        $otherPaymentMethod
            ->method('getGatewayConfig')
            ->willReturn($otherGatewayConfig)
        ;

        $otherGatewayConfig
            ->method('getFactoryName')
            ->willReturn('other')
        ;

        $payPalPaymentMethod
            ->method('getGatewayConfig')
            ->willReturn($payPalGatewayConfig)
        ;

        $payPalGatewayConfig
            ->method('getFactoryName')
            ->willReturn('sylius_paypal')
        ;

        $result = $this->payPalPaymentMethodProvider->provide();

        self::assertSame($payPalPaymentMethod, $result);
    }

    #[Test]
    public function it_throws_exception_when_no_enabled_payment_methods_exist(): void
    {
        $this->paymentMethodRepository
            ->method('findBy')
            ->with(['enabled' => true])
            ->willReturn([])
        ;

        $this->expectException(PayPalPaymentMethodNotFoundException::class);

        $this->payPalPaymentMethodProvider->provide();
    }

    #[Test]
    public function it_throws_exception_when_no_pay_pal_payment_method_exists(): void
    {
        $otherPaymentMethod = $this->createMock(PaymentMethodInterface::class);
        $otherGatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $this->paymentMethodRepository
            ->method('findBy')
            ->with(['enabled' => true])
            ->willReturn([$otherPaymentMethod])
        ;

        $otherPaymentMethod
            ->method('getGatewayConfig')
            ->willReturn($otherGatewayConfig)
        ;

        $otherGatewayConfig
            ->method('getFactoryName')
            ->willReturn('other')
        ;

        $this->expectException(PayPalPaymentMethodNotFoundException::class);

        $this->payPalPaymentMethodProvider->provide();
    }
}
