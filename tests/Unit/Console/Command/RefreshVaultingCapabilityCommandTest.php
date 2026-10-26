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

namespace Tests\Sylius\PayPalPlugin\Unit\Console\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Console\Command\RefreshVaultingCapabilityCommand;
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RefreshVaultingCapabilityCommandTest extends TestCase
{
    /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface>&MockObject */
    private PaymentMethodRepositoryInterface&MockObject $paymentMethodRepository;

    private VaultingCapabilityRefresherInterface&MockObject $refresher;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->refresher = $this->createMock(VaultingCapabilityRefresherInterface::class);

        $this->commandTester = new CommandTester(new RefreshVaultingCapabilityCommand($this->paymentMethodRepository, $this->refresher));
    }

    public function test_it_refreshes_every_paypal_payment_method(): void
    {
        $paymentMethod = $this->paymentMethod('sylius_paypal', 'PAYPAL');
        $this->paymentMethodRepository->method('findAll')->willReturn([$paymentMethod]);

        $this->refresher->expects(self::once())->method('refresh')->with($paymentMethod)->willReturn(true);

        self::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        self::assertStringContainsString('Vaulting is enabled for "PAYPAL".', $this->commandTester->getDisplay());
    }

    public function test_it_leaves_other_gateways_alone(): void
    {
        $this->paymentMethodRepository->method('findAll')->willReturn([$this->paymentMethod('stripe', 'STRIPE')]);

        $this->refresher->expects(self::never())->method('refresh');

        self::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
    }

    public function test_it_reports_a_payment_method_it_could_not_refresh_and_keeps_going(): void
    {
        $failing = $this->paymentMethod('sylius_paypal', 'PAYPAL_OLD');
        $working = $this->paymentMethod('sylius_paypal', 'PAYPAL');
        $this->paymentMethodRepository->method('findAll')->willReturn([$failing, $working]);

        $this->refresher
            ->expects(self::exactly(2))
            ->method('refresh')
            ->willReturnCallback(static function (PaymentMethodInterface $paymentMethod): bool {
                if ('PAYPAL_OLD' === $paymentMethod->getCode()) {
                    throw new \RuntimeException('PayPal rejected the credentials');
                }

                return false;
            })
        ;

        self::assertSame(Command::FAILURE, $this->commandTester->execute([]));
        self::assertStringContainsString('PAYPAL_OLD', $this->commandTester->getDisplay());
        self::assertStringContainsString('Vaulting is not enabled for "PAYPAL".', $this->commandTester->getDisplay());
    }

    private function paymentMethod(string $factoryName, string $code): PaymentMethodInterface
    {
        $gatewayConfig = $this->createStub(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);

        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $paymentMethod->method('getCode')->willReturn($code);

        return $paymentMethod;
    }
}
