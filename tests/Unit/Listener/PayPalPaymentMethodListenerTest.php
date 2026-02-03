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

namespace Tests\Sylius\PayPalPlugin\Unit\Listener;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Listener\PayPalPaymentMethodListener;
use Sylius\PayPalPlugin\Onboarding\Initiator\OnboardingInitiatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class PayPalPaymentMethodListenerTest extends TestCase
{
    private OnboardingInitiatorInterface&MockObject $onboardingInitiator;

    private PayPalPaymentMethodListener $payPalPaymentMethodListener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardingInitiator = $this->createMock(OnboardingInitiatorInterface::class);

        $this->payPalPaymentMethodListener = new PayPalPaymentMethodListener(
            $this->onboardingInitiator,
        );
    }

    #[Test]
    public function it_initiates_onboarding_when_creating_a_supported_payment_method(): void
    {
        $event = $this->createMock(ResourceControllerEvent::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $event
            ->expects(self::once())
            ->method('getSubject')
            ->willReturn($paymentMethod);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->expects(self::once())
            ->method('getFactoryName')
            ->willReturn('sylius_paypal');

        $this->onboardingInitiator
            ->expects(self::once())
            ->method('supports')
            ->with($paymentMethod)
            ->willReturn(true);

        $this->onboardingInitiator
            ->expects(self::once())
            ->method('initiate')
            ->with($paymentMethod)
            ->willReturn('https://example.com/onboarding-url');

        $event
            ->expects(self::once())
            ->method('setResponse')
            ->with($this->callback(function ($argument): bool {
                return $argument instanceof RedirectResponse && $argument->getTargetUrl() === 'https://example.com/onboarding-url';
            }));

        $this->payPalPaymentMethodListener->initializeCreate($event);
    }

    #[Test]
    public function it_throws_an_exception_if_subject_is_not_a_payment_method(): void
    {
        $event = $this->createMock(ResourceControllerEvent::class);

        $event
            ->expects(self::once())
            ->method('getSubject')
            ->willReturn(new \stdClass());

        $this->expectException(\InvalidArgumentException::class);

        $this->payPalPaymentMethodListener->initializeCreate($event);
    }

    #[Test]
    public function it_does_nothing_when_creating_an_unsupported_payment_method(): void
    {
        $event = $this->createMock(ResourceControllerEvent::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $event
            ->expects(self::once())
            ->method('getSubject')
            ->willReturn($paymentMethod);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->expects(self::once())
            ->method('getFactoryName')
            ->willReturn('sylius_paypal');

        $this->onboardingInitiator
            ->expects(self::once())
            ->method('supports')
            ->with($paymentMethod)
            ->willReturn(false);

        $event
            ->expects($this->never())
            ->method('setResponse');

        $this->payPalPaymentMethodListener->initializeCreate($event);
    }

    #[Test]
    public function it_does_nothing_if_payment_method_is_not_paypal(): void
    {
        $event = $this->createMock(ResourceControllerEvent::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $event
            ->expects(self::once())
            ->method('getSubject')
            ->willReturn($paymentMethod);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->expects(self::once())
            ->method('getFactoryName')
            ->willReturn('offline');

        $event
            ->expects($this->never())
            ->method('setResponse');

        $this->payPalPaymentMethodListener->initializeCreate($event);
    }

    #[Test]
    public function it_does_nothing_when_in_sandbox_mode(): void
    {
        $sandboxListener = new PayPalPaymentMethodListener(
            $this->onboardingInitiator,
            true, // isSandbox = true
        );

        $event = $this->createMock(ResourceControllerEvent::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $event
            ->expects(self::once())
            ->method('getSubject')
            ->willReturn($paymentMethod);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->expects(self::once())
            ->method('getFactoryName')
            ->willReturn('sylius_paypal');

        $this->onboardingInitiator
            ->expects($this->never())
            ->method('supports');

        $this->onboardingInitiator
            ->expects($this->never())
            ->method('initiate');

        $event
            ->expects($this->never())
            ->method('setResponse');

        $sandboxListener->initializeCreate($event);
    }
}
