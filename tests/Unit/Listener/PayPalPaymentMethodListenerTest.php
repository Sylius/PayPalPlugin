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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PayPalPaymentMethodListenerTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;

    private RequestStack&MockObject $requestStack;

    private PayPalPaymentMethodListener $payPalPaymentMethodListener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        $this->payPalPaymentMethodListener = new PayPalPaymentMethodListener(
            $this->urlGenerator,
            $this->requestStack,
        );
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
    public function it_redirects_to_the_onboarding_when_a_paypal_payment_method_is_created_by_hand(): void
    {
        $event = $this->createMock(ResourceControllerEvent::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $session = $this->createMock(SessionInterface::class);
        $flashBag = $this->createMock(FlashBagInterface::class);

        $event->method('getSubject')->willReturn($paymentMethod);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');

        $flashBag
            ->expects(self::once())
            ->method('add')
            ->with('info', 'sylius_paypal.create_paypal_payment_method_via_onboarding');

        $session->method('getBag')->with('flashes')->willReturn($flashBag);
        $this->requestStack->method('getSession')->willReturn($session);

        $this->urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('sylius_admin_payment_method_index')
            ->willReturn('http://redirect-url.com');

        $event
            ->expects(self::once())
            ->method('setResponse')
            ->with($this->callback(function (RedirectResponse $response): bool {
                return 'http://redirect-url.com' === $response->getTargetUrl();
            }));

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
}
