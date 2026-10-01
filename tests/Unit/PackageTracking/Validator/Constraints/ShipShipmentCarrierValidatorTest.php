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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\Validator\Constraints;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Repository\ShipmentRepositoryInterface;
use Sylius\PayPalPlugin\PackageTracking\Command\ShipShipmentWithCarrier;
use Sylius\PayPalPlugin\PackageTracking\Model\ShipmentTrackingData;
use Sylius\PayPalPlugin\PackageTracking\Provider\OrderPayPalPaymentProviderInterface;
use Sylius\PayPalPlugin\PackageTracking\Validator\Constraints\ShipmentTrackingCarrier;
use Sylius\PayPalPlugin\PackageTracking\Validator\Constraints\ShipShipmentCarrier;
use Sylius\PayPalPlugin\PackageTracking\Validator\Constraints\ShipShipmentCarrierValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ShipShipmentCarrierValidatorTest extends TestCase
{
    private ShipmentRepositoryInterface&MockObject $shipmentRepository;

    private OrderPayPalPaymentProviderInterface&MockObject $orderPayPalPaymentProvider;

    private ContextualValidatorInterface&MockObject $contextualValidator;

    private ShipShipmentCarrierValidator $validator;

    protected function setUp(): void
    {
        $this->shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $this->orderPayPalPaymentProvider = $this->createMock(OrderPayPalPaymentProviderInterface::class);
        $this->contextualValidator = $this->createMock(ContextualValidatorInterface::class);

        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getOrder')->willReturn($this->createMock(OrderInterface::class));
        $this->shipmentRepository->method('find')->willReturnMap([[7, $shipment]]);

        $validator = $this->createMock(ValidatorInterface::class);
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->method('getValidator')->willReturn($validator);
        $context->method('getGroup')->willReturn('sylius');
        $validator->method('inContext')->with($context)->willReturn($this->contextualValidator);

        $this->validator = new ShipShipmentCarrierValidator($this->shipmentRepository, $this->orderPayPalPaymentProvider);
        $this->validator->initialize($context);
    }

    #[Test]
    public function it_throws_an_exception_when_given_an_unsupported_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new ShipShipmentWithCarrier(7), new NotBlank());
    }

    #[Test]
    public function it_throws_an_exception_when_given_an_unsupported_value(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(new \stdClass(), new ShipShipmentCarrier());
    }

    #[Test]
    public function it_validates_the_carrier_of_an_order_paid_with_paypal(): void
    {
        $this->orderPayPalPaymentProvider->method('provide')->willReturn($this->createMock(PaymentInterface::class));

        $this->contextualValidator
            ->expects(self::once())
            ->method('validate')
            ->with(
                self::equalTo(new ShipmentTrackingData('OTHER', 'Local Courier')),
                self::equalTo(new ShipmentTrackingCarrier(groups: ['sylius'])),
                ['sylius'],
            )
        ;

        $this->validator->validate(new ShipShipmentWithCarrier(7, 'TRACK1', 'OTHER', 'Local Courier'), new ShipShipmentCarrier());
    }

    #[Test]
    public function it_validates_nothing_when_no_carrier_is_sent(): void
    {
        $this->shipmentRepository->expects(self::never())->method('find');
        $this->contextualValidator->expects(self::never())->method('validate');

        $this->validator->validate(new ShipShipmentWithCarrier(7, 'TRACK1', ' '), new ShipShipmentCarrier());
    }

    #[Test]
    public function it_validates_nothing_for_an_order_not_paid_with_paypal(): void
    {
        $this->orderPayPalPaymentProvider->method('provide')->willReturn(null);

        $this->contextualValidator->expects(self::never())->method('validate');

        $this->validator->validate(new ShipShipmentWithCarrier(7, 'TRACK1', 'NOT_A_CARRIER'), new ShipShipmentCarrier());
    }

    #[Test]
    public function it_validates_nothing_for_an_unknown_shipment(): void
    {
        $this->orderPayPalPaymentProvider->expects(self::never())->method('provide');
        $this->contextualValidator->expects(self::never())->method('validate');

        $this->validator->validate(new ShipShipmentWithCarrier(8, 'TRACK1', 'DHL'), new ShipShipmentCarrier());
    }
}
