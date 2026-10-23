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
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Console\Command\RegisterWebhookEventTypesCommand;
use Sylius\PayPalPlugin\Exception\PayPalWebhookNotRegisteredException;
use Sylius\PayPalPlugin\Registrar\SellerWebhookEventTypesRegistrarInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RegisterWebhookEventTypesCommandTest extends TestCase
{
    /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface>&MockObject */
    private PaymentMethodRepositoryInterface&MockObject $paymentMethodRepository;

    private SellerWebhookEventTypesRegistrarInterface&MockObject $registrar;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->registrar = $this->createMock(SellerWebhookEventTypesRegistrarInterface::class);

        $this->commandTester = new CommandTester(
            new RegisterWebhookEventTypesCommand($this->paymentMethodRepository, $this->registrar),
        );
    }

    public function test_it_updates_every_paypal_payment_method(): void
    {
        $paymentMethod = $this->paymentMethod('sylius_paypal');
        $this->paymentMethodRepository->method('findAll')->willReturn([$paymentMethod]);

        $this->registrar->expects(self::once())->method('register')->with($paymentMethod);

        self::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
    }

    public function test_it_leaves_other_gateways_alone(): void
    {
        $this->paymentMethodRepository->method('findAll')->willReturn([$this->paymentMethod('stripe')]);

        $this->registrar->expects(self::never())->method('register');

        self::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
    }

    public function test_it_reports_a_payment_method_it_could_not_update_and_keeps_going(): void
    {
        $failing = $this->paymentMethod('sylius_paypal', 'PAYPAL_OLD');
        $working = $this->paymentMethod('sylius_paypal');
        $this->paymentMethodRepository->method('findAll')->willReturn([$failing, $working]);

        $this->registrar
            ->method('register')
            ->willReturnCallback(static function (PaymentMethodInterface $paymentMethod): void {
                if ('PAYPAL_OLD' === $paymentMethod->getCode()) {
                    throw new PayPalWebhookNotRegisteredException('PAYPAL_OLD');
                }
            })
        ;

        self::assertSame(Command::FAILURE, $this->commandTester->execute([]));
        self::assertStringContainsString('PAYPAL_OLD', $this->commandTester->getDisplay());
    }

    private function paymentMethod(string $factoryName, string $code = 'PAYPAL'): PaymentMethodInterface&MockObject
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $paymentMethod->method('getCode')->willReturn($code);

        return $paymentMethod;
    }
}
