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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\Form\Extension;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Sylius\Bundle\AdminBundle\Form\Type\ShipmentShipType;
use Sylius\Bundle\ShippingBundle\Form\Type\ShipmentShipType as BaseShipmentShipType;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\Shipment;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTracking;
use Sylius\PayPalPlugin\PackageTracking\Form\Extension\ShipmentShipTypeExtension;
use Sylius\PayPalPlugin\PackageTracking\Form\Type\ShipmentTrackingType;
use Sylius\PayPalPlugin\PackageTracking\Manager\ShipmentTrackingManagerInterface;
use Sylius\PayPalPlugin\PackageTracking\Provider\CarrierProvider;
use Sylius\PayPalPlugin\PackageTracking\Provider\CarrierProviderInterface;
use Sylius\PayPalPlugin\PackageTracking\Provider\OrderPayPalPaymentProviderInterface;
use Sylius\PayPalPlugin\PackageTracking\Repository\ShipmentTrackingRepositoryInterface;
use Sylius\PayPalPlugin\PackageTracking\Validator\Constraints\ShipmentTrackingCarrier;
use Sylius\PayPalPlugin\PackageTracking\Validator\Constraints\ShipmentTrackingCarrierValidator;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ShipmentShipTypeExtensionTest extends TypeTestCase
{
    private ShipmentTrackingRepositoryInterface&MockObject $shipmentTrackingRepository;

    private ShipmentTrackingManagerInterface&MockObject $shipmentTrackingManager;

    private OrderPayPalPaymentProviderInterface&MockObject $orderPayPalPaymentProvider;

    private RequestStack $requestStack;

    protected function getExtensions(): array
    {
        $this->shipmentTrackingRepository = $this->createMock(ShipmentTrackingRepositoryInterface::class);
        $this->shipmentTrackingManager = $this->createMock(ShipmentTrackingManagerInterface::class);
        $this->orderPayPalPaymentProvider = $this->createMock(OrderPayPalPaymentProviderInterface::class);
        $this->requestStack = new RequestStack();

        $carrierProvider = new CarrierProvider(['FEDEX']);

        $validator = Validation::createValidatorBuilder()
            ->addXmlMapping(__DIR__ . '/../../../../../config/validation/ShipmentTrackingData.xml')
            ->setConstraintValidatorFactory($this->constraintValidatorFactory($carrierProvider))
            ->getValidator()
        ;

        return [
            new ValidatorExtension($validator),
            new PreloadedExtension(
                [
                    new BaseShipmentShipType(Shipment::class, ['sylius']),
                    new ShipmentShipType(),
                    new ShipmentTrackingType($carrierProvider, $this->translator()),
                ],
                [
                    ShipmentShipType::class => [new ShipmentShipTypeExtension(
                        $this->shipmentTrackingRepository,
                        $this->shipmentTrackingManager,
                        $this->orderPayPalPaymentProvider,
                        $this->requestStack,
                    )],
                ],
            ),
        ];
    }

    #[Test]
    public function it_does_not_add_the_carrier_fields_when_the_order_was_not_paid_with_paypal(): void
    {
        $shipment = $this->shipment();
        $this->orderPayPalPaymentProvider->method('provide')->willReturn(null);

        $form = $this->factory->create(ShipmentShipType::class, $shipment);

        self::assertFalse($form->has(ShipmentShipTypeExtension::TRACKING_FIELD_NAME));
    }

    #[Test]
    public function it_prefills_the_carrier_fields_from_the_existing_tracking(): void
    {
        $shipment = $this->shipment();
        $this->payPalPaidOrder();

        $tracking = new ShipmentTracking($shipment);
        $tracking->setCarrier(CarrierProviderInterface::OTHER_CARRIER_CODE);
        $tracking->setCarrierNameOther('Pigeon Post');
        $this->shipmentTrackingRepository->method('findOneByShipment')->with($shipment)->willReturn($tracking);

        $form = $this->factory->create(ShipmentShipType::class, $shipment);
        $trackingData = $form->get(ShipmentShipTypeExtension::TRACKING_FIELD_NAME)->getData();

        self::assertSame(CarrierProviderInterface::OTHER_CARRIER_CODE, $trackingData->getCarrier());
        self::assertSame('Pigeon Post', $trackingData->getCarrierNameOther());
    }

    #[Test]
    public function it_adds_a_validation_error_instead_of_persisting_when_the_carrier_is_missing(): void
    {
        $form = $this->submit(['tracking' => 'TRACK1', 'paypal_tracking' => ['carrier' => '', 'carrier_name_other' => '']]);

        self::assertFalse($form->isValid());
        self::assertSame(
            'sylius_paypal.shipment_tracking.carrier_required',
            (string) $form->get('paypal_tracking')->get('carrier')->getErrors()[0]->getMessage(),
        );
    }

    #[Test]
    public function it_adds_a_validation_error_when_the_other_carrier_has_no_name(): void
    {
        $form = $this->submit([
            'tracking' => 'TRACK1',
            'paypal_tracking' => ['carrier' => CarrierProviderInterface::OTHER_CARRIER_CODE, 'carrier_name_other' => ''],
        ]);

        self::assertFalse($form->isValid());
        self::assertSame(
            'sylius_paypal.shipment_tracking.carrier_name_other_required',
            (string) $form->get('paypal_tracking')->get('carrier_name_other')->getErrors()[0]->getMessage(),
        );
    }

    #[Test]
    public function it_does_not_require_a_carrier_when_no_tracking_number_is_given(): void
    {
        $this->shipmentTrackingManager->expects(self::never())->method('updateCarrier');

        $form = $this->submit(['tracking' => '', 'paypal_tracking' => ['carrier' => '', 'carrier_name_other' => '']]);

        self::assertTrue($form->isValid());
    }

    #[Test]
    public function it_persists_the_carrier_for_a_paypal_paid_order(): void
    {
        $this->shipmentTrackingManager
            ->expects(self::once())
            ->method('updateCarrier')
            ->with(self::isInstanceOf(ShipmentInterface::class), 'FEDEX', null)
        ;

        $form = $this->submit(['tracking' => 'TRACK1', 'paypal_tracking' => ['carrier' => 'FEDEX', 'carrier_name_other' => '']]);

        self::assertTrue($form->isValid());
    }

    #[Test]
    public function it_does_not_persist_anything_while_the_live_component_re_renders(): void
    {
        $request = Request::create('/_components/sylius_admin:shipment:ship_form');
        $request->attributes->set('_route', 'ux_live_component');
        $this->requestStack->push($request);

        $this->shipmentTrackingManager->expects(self::never())->method('updateCarrier');

        $this->submit(['tracking' => 'TRACK1', 'paypal_tracking' => ['carrier' => 'FEDEX', 'carrier_name_other' => '']]);
    }

    /** @param array<string, mixed> $data */
    private function submit(array $data): FormInterface
    {
        $this->payPalPaidOrder();
        $this->shipmentTrackingRepository->method('findOneByShipment')->willReturn(null);

        $form = $this->factory->create(ShipmentShipType::class, $this->shipment());
        $form->submit($data);

        return $form;
    }

    private function shipment(): ShipmentInterface
    {
        $shipment = new Shipment();
        $shipment->setOrder($this->createMock(OrderInterface::class));

        return $shipment;
    }

    private function payPalPaidOrder(): void
    {
        $this->orderPayPalPaymentProvider->method('provide')->willReturn($this->createMock(PaymentInterface::class));
    }

    private function translator(): TranslatorInterface&TranslatorBagInterface
    {
        /** @var TranslatorInterface&TranslatorBagInterface&MockObject $translator */
        $translator = $this->createMockForIntersectionOfInterfaces([TranslatorInterface::class, TranslatorBagInterface::class]);
        $translator->method('getCatalogue')->willReturn(new MessageCatalogue('en'));

        return $translator;
    }

    private function constraintValidatorFactory(CarrierProvider $carrierProvider): ConstraintValidatorFactoryInterface
    {
        return new class($carrierProvider) implements ConstraintValidatorFactoryInterface {
            private ConstraintValidatorFactory $defaultFactory;

            public function __construct(private readonly CarrierProvider $carrierProvider)
            {
                $this->defaultFactory = new ConstraintValidatorFactory();
            }

            public function getInstance(Constraint $constraint): ConstraintValidatorInterface
            {
                if ($constraint instanceof ShipmentTrackingCarrier) {
                    return new ShipmentTrackingCarrierValidator($this->carrierProvider);
                }

                return $this->defaultFactory->getInstance($constraint);
            }
        };
    }
}
