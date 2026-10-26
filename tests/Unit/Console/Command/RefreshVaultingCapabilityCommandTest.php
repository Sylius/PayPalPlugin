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

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Console\Command\RefreshVaultingCapabilityCommand;
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresherInterface;
use Sylius\PayPalPlugin\Repository\Query\PayPalPaymentMethodQueryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RefreshVaultingCapabilityCommandTest extends TestCase
{
    private PayPalPaymentMethodQueryInterface&MockObject $payPalPaymentMethodQuery;

    private VaultingCapabilityRefresherInterface&MockObject $refresher;

    private ObjectManager&MockObject $paymentMethodManager;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->payPalPaymentMethodQuery = $this->createMock(PayPalPaymentMethodQueryInterface::class);
        $this->refresher = $this->createMock(VaultingCapabilityRefresherInterface::class);
        $this->paymentMethodManager = $this->createMock(ObjectManager::class);

        $this->commandTester = new CommandTester(new RefreshVaultingCapabilityCommand(
            $this->payPalPaymentMethodQuery,
            $this->refresher,
            $this->paymentMethodManager,
        ));
    }

    public function test_it_refreshes_and_stores_every_paypal_payment_method(): void
    {
        $paymentMethod = $this->paymentMethod('PAYPAL');
        $this->payPalPaymentMethodQuery->method('findAll')->willReturn([$paymentMethod]);

        $this->refresher->expects(self::once())->method('refresh')->with($paymentMethod)->willReturn(true);
        $this->paymentMethodManager->expects(self::once())->method('flush');

        self::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        self::assertStringContainsString('Vaulting is enabled for "PAYPAL".', $this->commandTester->getDisplay());
    }

    public function test_it_reports_a_payment_method_it_could_not_refresh_and_keeps_going(): void
    {
        $failing = $this->paymentMethod('PAYPAL_OLD');
        $working = $this->paymentMethod('PAYPAL');
        $this->payPalPaymentMethodQuery->method('findAll')->willReturn([$failing, $working]);

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

    private function paymentMethod(string $code): PaymentMethodInterface
    {
        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $paymentMethod->method('getCode')->willReturn($code);

        return $paymentMethod;
    }
}
