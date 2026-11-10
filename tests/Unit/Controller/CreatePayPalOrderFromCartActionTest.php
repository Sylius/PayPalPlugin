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

namespace Tests\Sylius\PayPalPlugin\Unit\Controller;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Payment\Remover\OrderPaymentsRemoverInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Controller\CreatePayPalOrderFromCartAction;
use Sylius\PayPalPlugin\Model\PayPalVaultRequest;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalVaultRequestProviderInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolverInterface;
use Sylius\PayPalPlugin\Verifier\OrderOwnershipVerifierInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class CreatePayPalOrderFromCartActionTest extends TestCase
{
    public function test_it_refuses_venmo_for_a_cart_that_needs_vaulting(): void
    {
        $channel = $this->createStub(ChannelInterface::class);
        $order = $this->createStub(OrderInterface::class);
        $order->method('getChannel')->willReturn($channel);

        $orderProvider = $this->createStub(OrderProviderInterface::class);
        $orderProvider->method('provideOrderById')->willReturn($order);

        $payPalPaymentMethod = $this->createStub(PaymentMethodInterface::class);
        $payPalPaymentMethod->method('getGatewayConfig')->willReturn($this->createConfiguredStub(GatewayConfigInterface::class, ['getConfig' => ['venmo_enabled' => true]]));
        $payPalMethodsResolver = $this->createStub(PayPalPaymentMethodsResolverInterface::class);
        $payPalMethodsResolver->method('getInChannel')->with($channel)->willReturn([$payPalPaymentMethod]);

        $vaultRequestProvider = $this->createMock(PayPalVaultRequestProviderInterface::class);
        $vaultRequestProvider->method('isVaultingRequirementMet')->willReturn(true);
        $vaultRequestProvider
            ->expects(self::once())
            ->method('provide')
            ->with($order, $payPalPaymentMethod)
            ->willReturn(new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID))
        ;

        $capturePaymentResolver = $this->createMock(CapturePaymentResolverInterface::class);
        $capturePaymentResolver->expects(self::never())->method('resolve');

        $action = new CreatePayPalOrderFromCartAction(
            $this->createStub(ObjectManager::class),
            $orderProvider,
            $capturePaymentResolver,
            $this->createStub(OrderPaymentsRemoverInterface::class),
            $this->createStub(OrderProcessorInterface::class),
            $payPalMethodsResolver,
            $this->createStub(OrderOwnershipVerifierInterface::class),
            $vaultRequestProvider,
        );

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/create-pay-pal-order-from-cart/1', 'POST');
        $request->query->set('paymentSource', 'venmo');
        $request->attributes->set('id', 1);
        $request->setSession($session);

        self::assertSame(Response::HTTP_BAD_REQUEST, $action($request)->getStatusCode());
        self::assertSame(['sylius_paypal.paypal_wallet_required'], $session->getFlashBag()->get('error'));
    }

    public function test_it_refuses_pay_later_for_a_cart_that_needs_vaulting(): void
    {
        $channel = $this->createStub(ChannelInterface::class);
        $order = $this->createStub(OrderInterface::class);
        $order->method('getChannel')->willReturn($channel);

        $orderProvider = $this->createStub(OrderProviderInterface::class);
        $orderProvider->method('provideOrderById')->willReturn($order);

        $payPalPaymentMethod = $this->createStub(PaymentMethodInterface::class);
        $payPalPaymentMethod->method('getGatewayConfig')->willReturn($this->createConfiguredStub(GatewayConfigInterface::class, ['getConfig' => ['pay_later_enabled' => true]]));
        $payPalMethodsResolver = $this->createStub(PayPalPaymentMethodsResolverInterface::class);
        $payPalMethodsResolver->method('getInChannel')->with($channel)->willReturn([$payPalPaymentMethod]);

        $vaultRequestProvider = $this->createMock(PayPalVaultRequestProviderInterface::class);
        $vaultRequestProvider->method('isVaultingRequirementMet')->willReturn(true);
        $vaultRequestProvider
            ->expects(self::once())
            ->method('provide')
            ->with($order, $payPalPaymentMethod)
            ->willReturn(new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID))
        ;

        $capturePaymentResolver = $this->createMock(CapturePaymentResolverInterface::class);
        $capturePaymentResolver->expects(self::never())->method('resolve');

        $action = new CreatePayPalOrderFromCartAction(
            $this->createStub(ObjectManager::class),
            $orderProvider,
            $capturePaymentResolver,
            $this->createStub(OrderPaymentsRemoverInterface::class),
            $this->createStub(OrderProcessorInterface::class),
            $payPalMethodsResolver,
            $this->createStub(OrderOwnershipVerifierInterface::class),
            $vaultRequestProvider,
        );

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/create-pay-pal-order-from-cart/1', 'POST');
        $request->query->set('paymentSource', 'paylater');
        $request->attributes->set('id', 1);
        $request->setSession($session);

        self::assertSame(Response::HTTP_BAD_REQUEST, $action($request)->getStatusCode());
        self::assertSame(['sylius_paypal.paypal_wallet_required'], $session->getFlashBag()->get('error'));
    }

    public function test_it_refuses_the_paypal_wallet_for_a_cart_that_needs_vaulting_the_merchant_cannot_do(): void
    {
        $channel = $this->createStub(ChannelInterface::class);
        $order = $this->createStub(OrderInterface::class);
        $order->method('getChannel')->willReturn($channel);

        $orderProvider = $this->createStub(OrderProviderInterface::class);
        $orderProvider->method('provideOrderById')->willReturn($order);

        $payPalPaymentMethod = $this->createStub(PaymentMethodInterface::class);
        $payPalMethodsResolver = $this->createStub(PayPalPaymentMethodsResolverInterface::class);
        $payPalMethodsResolver->method('getInChannel')->willReturn([$payPalPaymentMethod]);

        $vaultRequestProvider = $this->createMock(PayPalVaultRequestProviderInterface::class);
        $vaultRequestProvider
            ->expects(self::once())
            ->method('isVaultingRequirementMet')
            ->with($order, $payPalPaymentMethod)
            ->willReturn(false)
        ;

        $capturePaymentResolver = $this->createMock(CapturePaymentResolverInterface::class);
        $capturePaymentResolver->expects(self::never())->method('resolve');

        $action = new CreatePayPalOrderFromCartAction(
            $this->createStub(ObjectManager::class),
            $orderProvider,
            $capturePaymentResolver,
            $this->createStub(OrderPaymentsRemoverInterface::class),
            $this->createStub(OrderProcessorInterface::class),
            $payPalMethodsResolver,
            $this->createStub(OrderOwnershipVerifierInterface::class),
            $vaultRequestProvider,
        );

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/create-pay-pal-order-from-cart/1', 'POST');
        $request->attributes->set('id', 1);
        $request->setSession($session);

        self::assertSame(Response::HTTP_BAD_REQUEST, $action($request)->getStatusCode());
        self::assertSame(['sylius_paypal.vaulting_not_available'], $session->getFlashBag()->get('error'));
    }
}
