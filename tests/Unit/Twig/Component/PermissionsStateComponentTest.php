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

namespace Tests\Sylius\PayPalPlugin\Unit\Twig\Component;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Checker\PermissionsStateCheckerInterface;
use Sylius\PayPalPlugin\Model\PermissionsState;
use Sylius\PayPalPlugin\Twig\Component\PermissionsStateComponent;

final class PermissionsStateComponentTest extends TestCase
{
    /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface>&MockObject */
    private PaymentMethodRepositoryInterface&MockObject $paymentMethodRepository;

    private PermissionsStateCheckerInterface&MockObject $permissionsStateChecker;

    private PermissionsStateComponent $component;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->permissionsStateChecker = $this->createMock(PermissionsStateCheckerInterface::class);

        $this->component = new PermissionsStateComponent($this->paymentMethodRepository, $this->permissionsStateChecker);
        $this->component->paymentMethodId = 1;
    }

    public function test_it_gives_the_state_of_the_permissions_of_a_paypal_payment_method(): void
    {
        $paymentMethod = $this->paymentMethod('sylius_paypal');
        $this->paymentMethodRepository->method('find')->with(1)->willReturn($paymentMethod);
        $this->permissionsStateChecker->method('check')->with($paymentMethod)->willReturn(PermissionsState::unknown());

        self::assertEquals(PermissionsState::unknown(), $this->component->getState());
    }

    public function test_it_gives_no_state_for_another_gateway(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn($this->paymentMethod('offline'));

        $this->permissionsStateChecker->expects(self::never())->method('check');

        self::assertNull($this->component->getState());
    }

    public function test_it_gives_no_state_for_a_payment_method_that_does_not_exist(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn(null);

        self::assertNull($this->component->getState());
    }

    private function paymentMethod(string $factoryName): PaymentMethodInterface
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName($factoryName);

        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        return $paymentMethod;
    }
}
