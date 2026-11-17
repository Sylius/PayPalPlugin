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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Sylius\Bundle\ApiBundle\Command\Payment\AddPaymentRequest;
use Sylius\Bundle\ApiBundle\Command\Payment\UpdatePaymentRequest;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Checker\PaymentAuthorizationCheckerInterface;
use Sylius\PayPalPlugin\Checker\PaymentSourceEnabledCheckerInterface;
use Sylius\PayPalPlugin\CommandProvider\CaptureAuthorizationPaymentRequestCommandProvider;
use Sylius\PayPalPlugin\CommandProvider\RefundLateCapturePaymentRequestCommandProvider;
use Sylius\PayPalPlugin\CommandProvider\VoidAuthorizationPaymentRequestCommandProvider;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProvider;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolverInterface;
use Sylius\PayPalPlugin\Validator\Constraints\ShopPaymentRequestEligibility;
use Sylius\PayPalPlugin\Validator\Constraints\ShopPaymentRequestEligibilityValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/** @extends ConstraintValidatorTestCase<ShopPaymentRequestEligibilityValidator> */
final class ShopPaymentRequestEligibilityValidatorTest extends ConstraintValidatorTestCase
{
    private PaymentMethodRepositoryInterface&MockObject $paymentMethodRepository;

    private PaymentRequestRepositoryInterface&MockObject $paymentRequestRepository;

    private PayPalPaymentMethodsResolverInterface&MockObject $payPalPaymentMethodsResolver;

    private PaymentSourceEnabledCheckerInterface&MockObject $paymentSourceEnabledChecker;

    private bool $authorizeEnabled = false;

    private PaymentMethodInterface&MockObject $payPalPaymentMethod;

    private ChannelInterface&MockObject $channel;

    protected function createValidator(): ConstraintValidatorInterface
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->paymentRequestRepository = $this->createMock(PaymentRequestRepositoryInterface::class);
        $this->payPalPaymentMethodsResolver = $this->createMock(PayPalPaymentMethodsResolverInterface::class);
        $this->paymentSourceEnabledChecker = $this->createMock(PaymentSourceEnabledCheckerInterface::class);

        $this->payPalPaymentMethod = $this->paymentMethod('sylius_paypal');
        $this->channel = $this->createMock(ChannelInterface::class);

        $this->paymentMethodRepository->method('findOneBy')->willReturnCallback(fn (array $criteria): ?PaymentMethodInterface => match ($criteria['code']) {
            'PAYPAL' => $this->payPalPaymentMethod,
            'CASH_ON_DELIVERY' => $this->paymentMethod('offline'),
            default => null,
        });

        $order = $this->createMock(OrderInterface::class);
        $order->method('getChannel')->willReturn($this->channel);
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $paymentRepository->method('findOneByOrderToken')->willReturnMap([[7, 'TOKEN', $payment]]);

        $this->payPalPaymentMethodsResolver->method('getInChannel')->with($this->channel)->willReturn([$this->payPalPaymentMethod]);
        $this->paymentSourceEnabledChecker->method('isEnabled')->willReturnCallback(
            fn (string $paymentSource, PaymentMethodInterface $paymentMethod): bool => 'venmo' !== $paymentSource && $paymentMethod === $this->payPalPaymentMethod,
        );

        $paymentAuthorizationChecker = $this->createMock(PaymentAuthorizationCheckerInterface::class);
        $paymentAuthorizationChecker->method('isAuthorizeEnabled')->willReturnCallback(fn (): bool => $this->authorizeEnabled);

        return new ShopPaymentRequestEligibilityValidator(
            $this->paymentMethodRepository,
            $paymentRepository,
            $this->paymentRequestRepository,
            $this->payPalPaymentMethodsResolver,
            new PayPalPaymentSourceProvider(),
            $this->paymentSourceEnabledChecker,
            $paymentAuthorizationChecker,
        );
    }

    #[Test]
    public function it_throws_an_exception_when_given_an_unsupported_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate($this->addPaymentRequest(), new NotBlank());
    }

    #[Test]
    public function it_throws_an_exception_when_given_an_unsupported_value(): void
    {
        $this->expectExceptionObject(new UnexpectedValueException(new \stdClass(), AddPaymentRequest::class . '|' . UpdatePaymentRequest::class));

        $this->validator->validate(new \stdClass(), new ShopPaymentRequestEligibility());
    }

    #[Test]
    public function it_accepts_an_enabled_payment_source(): void
    {
        $this->validator->validate($this->addPaymentRequest(), new ShopPaymentRequestEligibility());

        $this->assertNoViolation();
    }

    #[Test]
    public function it_accepts_the_default_action(): void
    {
        $this->validator->validate($this->addPaymentRequest(action: null), new ShopPaymentRequestEligibility());

        $this->assertNoViolation();
    }

    #[Test]
    public function it_accepts_the_status_action_without_a_payload(): void
    {
        $this->validator->validate($this->addPaymentRequest(action: 'status', payload: null), new ShopPaymentRequestEligibility());

        $this->assertNoViolation();
    }

    #[Test]
    public function it_refuses_the_status_action_for_a_paypal_payment_method_not_available_in_the_channel_of_the_order(): void
    {
        $constraint = new ShopPaymentRequestEligibility();
        $this->payPalPaymentMethod = $this->paymentMethod('sylius_paypal');

        $this->validator->validate($this->addPaymentRequest(action: 'status', payload: null), $constraint);

        $this->buildViolation($constraint->paymentMethodNotAvailableMessage)
            ->atPath('property.path.paymentMethodCode')
            ->assertRaised()
        ;
    }

    #[Test]
    public function it_accepts_the_authorize_action_for_a_payment_method_that_authorizes_payments(): void
    {
        $this->authorizeEnabled = true;

        $this->validator->validate($this->addPaymentRequest(action: 'authorize'), new ShopPaymentRequestEligibility());

        $this->assertNoViolation();
    }

    /** @return iterable<string, array{bool, string, string}> */
    public static function actionsNotMatchingThePaymentMethod(): iterable
    {
        yield 'authorize for a payment method that captures payments' => [false, 'authorize', 'capture'];
        yield 'capture for a payment method that authorizes payments' => [true, 'capture', 'authorize'];
    }

    #[Test]
    #[DataProvider('actionsNotMatchingThePaymentMethod')]
    public function it_refuses_an_action_not_matching_the_payment_method(bool $authorizeEnabled, string $action, string $expectedAction): void
    {
        $constraint = new ShopPaymentRequestEligibility();
        $this->authorizeEnabled = $authorizeEnabled;

        $this->validator->validate($this->addPaymentRequest(action: $action), $constraint);

        $this->buildViolation($constraint->actionNotMatchingPaymentMethodMessage)
            ->setParameter('{{ action }}', $action)
            ->setParameter('{{ expected_action }}', $expectedAction)
            ->atPath('property.path.action')
            ->assertRaised()
        ;
    }

    #[Test]
    public function it_validates_nothing_for_a_payment_method_other_than_paypal(): void
    {
        $this->validator->validate(
            $this->addPaymentRequest(paymentMethodCode: 'CASH_ON_DELIVERY', action: 'refund', payload: null),
            new ShopPaymentRequestEligibility(),
        );

        $this->assertNoViolation();
    }

    #[Test]
    public function it_validates_nothing_for_an_unknown_payment_method(): void
    {
        $this->validator->validate($this->addPaymentRequest(paymentMethodCode: 'UNKNOWN', payload: null), new ShopPaymentRequestEligibility());

        $this->assertNoViolation();
    }

    /** @return iterable<string, array{string}> */
    public static function refusedActions(): iterable
    {
        yield 'refund' => [PaymentRequestInterface::ACTION_REFUND];
        yield 'notify' => [PaymentRequestInterface::ACTION_NOTIFY];
        yield 'void authorization' => [VoidAuthorizationPaymentRequestCommandProvider::ACTION];
        yield 'capture authorization' => [CaptureAuthorizationPaymentRequestCommandProvider::ACTION];
        yield 'refund late capture' => [RefundLateCapturePaymentRequestCommandProvider::ACTION];
    }

    #[Test]
    #[DataProvider('refusedActions')]
    public function it_refuses_an_action_other_than_capture_authorize_and_status(string $action): void
    {
        $constraint = new ShopPaymentRequestEligibility();

        $this->validator->validate($this->addPaymentRequest(action: $action), $constraint);

        $this->buildViolation($constraint->actionNotAllowedMessage)
            ->setParameter('{{ action }}', $action)
            ->atPath('property.path.action')
            ->assertRaised()
        ;
    }

    #[Test]
    public function it_refuses_a_paypal_payment_method_not_available_in_the_channel_of_the_order(): void
    {
        $constraint = new ShopPaymentRequestEligibility();
        $this->payPalPaymentMethod = $this->paymentMethod('sylius_paypal');

        $this->validator->validate($this->addPaymentRequest(), $constraint);

        $this->buildViolation($constraint->paymentMethodNotAvailableMessage)
            ->atPath('property.path.paymentMethodCode')
            ->assertRaised()
        ;
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidPayloads(): iterable
    {
        yield 'no payload' => [null];
        yield 'not an array' => ['paypal'];
        yield 'no payment source' => [[]];
        yield 'payment source that is not a string' => [['payment_source' => ['paypal']]];
        yield 'another key' => [['payment_source' => 'paypal', 'return_url' => 'https://attacker.example']];
    }

    #[Test]
    #[DataProvider('invalidPayloads')]
    public function it_refuses_a_payload_with_anything_but_the_payment_source(mixed $payload): void
    {
        $constraint = new ShopPaymentRequestEligibility();

        $this->validator->validate($this->addPaymentRequest(payload: $payload), $constraint);

        $this->buildViolation($constraint->payloadInvalidMessage)
            ->atPath('property.path.payload')
            ->assertRaised()
        ;
    }

    /** @return iterable<string, array{string}> */
    public static function unavailablePaymentSources(): iterable
    {
        yield 'unsupported by PayPal' => ['bitcoin'];
        yield 'not enabled' => ['venmo'];
    }

    #[Test]
    #[DataProvider('unavailablePaymentSources')]
    public function it_refuses_an_unavailable_payment_source(string $paymentSource): void
    {
        $constraint = new ShopPaymentRequestEligibility();

        $this->validator->validate($this->addPaymentRequest(payload: ['payment_source' => $paymentSource]), $constraint);

        $this->buildViolation($constraint->paymentSourceNotAvailableMessage)
            ->setParameter('{{ payment_source }}', $paymentSource)
            ->atPath('property.path.payload.payment_source')
            ->assertRaised()
        ;
    }

    #[Test]
    public function it_refuses_an_unavailable_payment_source_when_a_new_payment_request_is_updated(): void
    {
        $constraint = new ShopPaymentRequestEligibility();
        $this->paymentRequestRepository->method('find')->with('HASH')->willReturn(
            $this->paymentRequest(PaymentRequestInterface::STATE_NEW, $this->payPalPaymentMethod),
        );

        $this->validator->validate(new UpdatePaymentRequest('HASH', ['payment_source' => 'venmo']), $constraint);

        $this->buildViolation($constraint->paymentSourceNotAvailableMessage)
            ->setParameter('{{ payment_source }}', 'venmo')
            ->atPath('property.path.payload.payment_source')
            ->assertRaised()
        ;
    }

    #[Test]
    public function it_accepts_an_enabled_payment_source_when_a_new_payment_request_is_updated(): void
    {
        $this->paymentRequestRepository->method('find')->with('HASH')->willReturn(
            $this->paymentRequest(PaymentRequestInterface::STATE_NEW, $this->payPalPaymentMethod),
        );

        $this->validator->validate(new UpdatePaymentRequest('HASH', ['payment_source' => 'paypal']), new ShopPaymentRequestEligibility());

        $this->assertNoViolation();
    }

    #[Test]
    public function it_ignores_the_payload_of_a_new_status_payment_request(): void
    {
        $this->paymentRequestRepository->method('find')->with('HASH')->willReturn(
            $this->paymentRequest(PaymentRequestInterface::STATE_NEW, $this->payPalPaymentMethod, PaymentRequestInterface::ACTION_STATUS),
        );

        $this->validator->validate(new UpdatePaymentRequest('HASH'), new ShopPaymentRequestEligibility());

        $this->assertNoViolation();
    }

    #[Test]
    public function it_ignores_the_payload_of_a_processing_payment_request(): void
    {
        $this->paymentRequestRepository->method('find')->with('HASH')->willReturn(
            $this->paymentRequest(PaymentRequestInterface::STATE_PROCESSING, $this->payPalPaymentMethod),
        );

        $this->validator->validate(new UpdatePaymentRequest('HASH'), new ShopPaymentRequestEligibility());

        $this->assertNoViolation();
    }

    #[Test]
    public function it_validates_nothing_when_a_payment_request_of_another_payment_method_is_updated(): void
    {
        $this->paymentRequestRepository->method('find')->with('HASH')->willReturn(
            $this->paymentRequest(PaymentRequestInterface::STATE_NEW, $this->paymentMethod('offline')),
        );

        $this->validator->validate(new UpdatePaymentRequest('HASH'), new ShopPaymentRequestEligibility());

        $this->assertNoViolation();
    }

    #[Test]
    public function it_validates_nothing_when_an_unknown_payment_request_is_updated(): void
    {
        $this->validator->validate(new UpdatePaymentRequest('HASH'), new ShopPaymentRequestEligibility());

        $this->assertNoViolation();
    }

    private function addPaymentRequest(
        string $paymentMethodCode = 'PAYPAL',
        ?string $action = 'capture',
        mixed $payload = ['payment_source' => 'paypal'],
    ): AddPaymentRequest {
        return new AddPaymentRequest('TOKEN', 7, $paymentMethodCode, $action, $payload);
    }

    private function paymentMethod(string $factoryName): PaymentMethodInterface&MockObject
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        return $paymentMethod;
    }

    private function paymentRequest(
        string $state,
        PaymentMethodInterface $paymentMethod,
        string $action = PaymentRequestInterface::ACTION_CAPTURE,
    ): PaymentRequestInterface&MockObject {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getState')->willReturn($state);
        $paymentRequest->method('getAction')->willReturn($action);
        $paymentRequest->method('getMethod')->willReturn($paymentMethod);

        return $paymentRequest;
    }
}
