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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Controller\EnableSellerAction;
use Sylius\PayPalPlugin\Enabler\PaymentMethodEnablerInterface;
use Sylius\PayPalPlugin\Exception\PaymentMethodCouldNotBeEnabledException;
use Sylius\PayPalPlugin\Model\Capability;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Provider\HostProvider;
use Sylius\PayPalPlugin\Provider\OnboardingStatusMessagesProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class EnableSellerActionTest extends TestCase
{
    /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface>&MockObject */
    private PaymentMethodRepositoryInterface&MockObject $paymentMethodRepository;

    private PaymentMethodEnablerInterface&MockObject $paymentMethodEnabler;

    private EnableSellerAction $action;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->paymentMethodEnabler = $this->createMock(PaymentMethodEnablerInterface::class);

        $this->action = new EnableSellerAction(
            $this->paymentMethodRepository,
            $this->paymentMethodEnabler,
            new OnboardingStatusMessagesProvider(new HostProvider()),
        );
    }

    #[Test]
    public function it_lists_only_the_reasons_blocking_the_payment_method_and_not_the_vaulting_status(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn($this->paymentMethod());
        $this->paymentMethodEnabler
            ->method('enable')
            ->willThrowException(new PaymentMethodCouldNotBeEnabledException(new OnboardingStatus(false, true, [Capability::PAYPAL_WALLET_VAULTING_ADVANCED => 'NEED_DATA'])))
        ;

        $request = $this->request();
        ($this->action)($request);

        self::assertSame(
            ['error' => [[
                'message' => 'sylius_paypal.seller_onboarding_payments_not_receivable',
                'parameters' => ['%paypal_url%' => 'https://www.sandbox.paypal.com'],
            ]]],
            $this->flashBag($request)->all(),
        );
    }

    #[Test]
    public function it_tells_the_payment_method_was_not_enabled_when_only_vaulting_is_not_active(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn($this->paymentMethod());
        $this->paymentMethodEnabler
            ->method('enable')
            ->willThrowException(new PaymentMethodCouldNotBeEnabledException(new OnboardingStatus(true, true, [Capability::PAYPAL_WALLET_VAULTING_ADVANCED => 'NEED_DATA'])))
        ;

        $request = $this->request();
        ($this->action)($request);

        self::assertSame(['error' => ['sylius_paypal.payment_not_enabled']], $this->flashBag($request)->all());
    }

    private function paymentMethod(): PaymentMethodInterface
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius_paypal');
        $gatewayConfig->setConfig(['sandbox' => true]);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }

    private function request(): Request
    {
        $request = new Request(attributes: ['id' => 1]);
        $request->headers->set('referer', '/admin/payment-methods/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    private function flashBag(Request $request): FlashBagInterface
    {
        /** @var FlashBagInterface $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');

        return $flashBag;
    }
}
