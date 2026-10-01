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
use Sylius\PayPalPlugin\PackageTracking\Model\ShipmentTrackingData;
use Sylius\PayPalPlugin\PackageTracking\Provider\CarrierProvider;
use Sylius\PayPalPlugin\PackageTracking\Provider\CarrierProviderInterface;
use Sylius\PayPalPlugin\PackageTracking\Validator\Constraints\ShipmentTrackingCarrier;
use Sylius\PayPalPlugin\PackageTracking\Validator\Constraints\ShipmentTrackingCarrierValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/** @extends ConstraintValidatorTestCase<ShipmentTrackingCarrierValidator> */
final class ShipmentTrackingCarrierValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ShipmentTrackingCarrierValidator(new CarrierProvider(['FEDEX']));
    }

    #[Test]
    public function it_throws_an_exception_when_given_an_unsupported_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new ShipmentTrackingData(), new NotBlank());
    }

    #[Test]
    public function it_throws_an_exception_when_given_an_unsupported_value(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(new \stdClass(), new ShipmentTrackingCarrier());
    }

    #[Test]
    public function it_does_nothing_when_there_is_no_data(): void
    {
        $this->validator->validate(null, new ShipmentTrackingCarrier());

        $this->assertNoViolation();
    }

    #[Test]
    public function it_does_not_require_a_carrier_when_there_is_no_tracking_number(): void
    {
        $this->validator->validate(new ShipmentTrackingData(), new ShipmentTrackingCarrier());

        $this->assertNoViolation();
    }

    #[Test]
    public function it_requires_a_carrier_when_a_tracking_number_is_given(): void
    {
        $constraint = new ShipmentTrackingCarrier();

        $this->validator->validate(new ShipmentTrackingData(null, null, 'TRACK1'), $constraint);

        $this->buildViolation($constraint->carrierRequiredMessage)
            ->atPath('property.path.carrier')
            ->assertRaised()
        ;
    }

    #[Test]
    public function it_requires_the_carrier_name_when_the_other_carrier_is_selected(): void
    {
        $constraint = new ShipmentTrackingCarrier();

        $this->validator->validate(
            new ShipmentTrackingData(CarrierProviderInterface::OTHER_CARRIER_CODE, null, 'TRACK1'),
            $constraint,
        );

        $this->buildViolation($constraint->carrierNameOtherRequiredMessage)
            ->atPath('property.path.carrierNameOther')
            ->assertRaised()
        ;
    }

    #[Test]
    public function it_accepts_the_other_carrier_with_a_carrier_name(): void
    {
        $this->validator->validate(
            new ShipmentTrackingData(CarrierProviderInterface::OTHER_CARRIER_CODE, 'Pigeon Post', 'TRACK1'),
            new ShipmentTrackingCarrier(),
        );

        $this->assertNoViolation();
    }

    #[Test]
    public function it_accepts_a_known_carrier_with_a_tracking_number(): void
    {
        $this->validator->validate(new ShipmentTrackingData('FEDEX', null, 'TRACK1'), new ShipmentTrackingCarrier());

        $this->assertNoViolation();
    }
}
