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

namespace Tests\Sylius\PayPalPlugin\Unit\Validator\Constraints;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Validator\Constraints\OnlyOneEnabledPayPalPaymentMethod;
use Sylius\PayPalPlugin\Validator\Constraints\OnlyOneEnabledPayPalPaymentMethodValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class OnlyOneEnabledPayPalPaymentMethodValidatorTest extends TestCase
{
    private PaymentMethodRepositoryInterface&MockObject $paymentMethodRepository;

    private ExecutionContextInterface&MockObject $context;

    private OnlyOneEnabledPayPalPaymentMethodValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->context = $this->createMock(ExecutionContextInterface::class);

        $this->validator = new OnlyOneEnabledPayPalPaymentMethodValidator(
            $this->paymentMethodRepository,
        );
        $this->validator->initialize($this->context);
    }

    #[Test]
    public function it_throws_exception_for_wrong_constraint(): void
    {
        $wrongConstraint = $this->createMock(Constraint::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate($paymentMethod, $wrongConstraint);
    }

    #[Test]
    public function it_throws_exception_for_wrong_value(): void
    {
        $constraint = new OnlyOneEnabledPayPalPaymentMethod();

        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(new \stdClass(), $constraint);
    }

    #[Test]
    public function it_does_nothing_for_non_paypal_methods(): void
    {
        $constraint = new OnlyOneEnabledPayPalPaymentMethod();
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig)
        ;

        $gatewayConfig
            ->expects(self::once())
            ->method('getFactoryName')
            ->willReturn('offline')
        ;

        $this->context
            ->expects(self::never())
            ->method('buildViolation')
        ;

        $this->validator->validate($paymentMethod, $constraint);
    }

    #[Test]
    public function it_does_nothing_when_paypal_method_is_disabled(): void
    {
        $constraint = new OnlyOneEnabledPayPalPaymentMethod();
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig)
        ;

        $gatewayConfig
            ->expects(self::once())
            ->method('getFactoryName')
            ->willReturn('sylius_paypal')
        ;

        $paymentMethod
            ->expects(self::once())
            ->method('isEnabled')
            ->willReturn(false)
        ;

        $this->context
            ->expects(self::never())
            ->method('buildViolation')
        ;

        $this->validator->validate($paymentMethod, $constraint);
    }

    #[Test]
    public function it_passes_when_no_other_paypal_method_is_enabled(): void
    {
        $constraint = new OnlyOneEnabledPayPalPaymentMethod();
        $paymentMethod = $this->createEnabledPayPalPaymentMethod(1);
        $offlinePaymentMethod = $this->createOfflinePaymentMethod(2);

        $this->paymentMethodRepository
            ->expects(self::once())
            ->method('findBy')
            ->with(['enabled' => true])
            ->willReturn([$paymentMethod, $offlinePaymentMethod])
        ;

        $this->context
            ->expects(self::never())
            ->method('buildViolation')
        ;

        $this->validator->validate($paymentMethod, $constraint);
    }

    #[Test]
    public function it_adds_violation_when_another_paypal_method_is_enabled(): void
    {
        $constraint = new OnlyOneEnabledPayPalPaymentMethod();
        $paymentMethod = $this->createEnabledPayPalPaymentMethod(1);
        $otherPayPalMethod = $this->createEnabledPayPalPaymentMethod(2);

        $this->paymentMethodRepository
            ->expects(self::once())
            ->method('findBy')
            ->with(['enabled' => true])
            ->willReturn([$paymentMethod, $otherPayPalMethod])
        ;

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);

        $this->context
            ->expects(self::once())
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($violationBuilder)
        ;

        $violationBuilder
            ->expects(self::once())
            ->method('atPath')
            ->with('enabled')
            ->willReturn($violationBuilder)
        ;

        $violationBuilder
            ->expects(self::once())
            ->method('addViolation')
        ;

        $this->validator->validate($paymentMethod, $constraint);
    }

    #[Test]
    public function it_skips_the_same_payment_method_when_checking(): void
    {
        $constraint = new OnlyOneEnabledPayPalPaymentMethod();
        $paymentMethod = $this->createEnabledPayPalPaymentMethod(1, true);

        $this->paymentMethodRepository
            ->expects(self::once())
            ->method('findBy')
            ->with(['enabled' => true])
            ->willReturn([$paymentMethod])
        ;

        $this->context
            ->expects(self::never())
            ->method('buildViolation');

        $this->validator->validate($paymentMethod, $constraint);
    }

    private function createEnabledPayPalPaymentMethod(int $id): PaymentMethodInterface&MockObject
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $paymentMethod->method('getId')->willReturn($id);
        $paymentMethod->method('isEnabled')->willReturn(true);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');

        return $paymentMethod;
    }

    private function createOfflinePaymentMethod(int $id): PaymentMethodInterface&MockObject
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $paymentMethod->method('getId')->willReturn($id);
        $paymentMethod->method('isEnabled')->willReturn(true);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $gatewayConfig->method('getFactoryName')->willReturn('offline');

        return $paymentMethod;
    }
}
