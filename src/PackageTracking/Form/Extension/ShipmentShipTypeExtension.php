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

namespace Sylius\PayPalPlugin\PackageTracking\Form\Extension;

use Sylius\Bundle\AdminBundle\Form\Type\ShipmentShipType;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Form\Type\ShipmentTrackingType;
use Sylius\PayPalPlugin\PackageTracking\Manager\ShipmentTrackingManagerInterface;
use Sylius\PayPalPlugin\PackageTracking\Model\ShipmentTrackingData;
use Sylius\PayPalPlugin\PackageTracking\Provider\OrderPayPalPaymentProviderInterface;
use Sylius\PayPalPlugin\PackageTracking\Repository\ShipmentTrackingRepositoryInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;

final class ShipmentShipTypeExtension extends AbstractTypeExtension
{
    public const TRACKING_FIELD_NAME = 'paypal_tracking';

    private const LIVE_COMPONENT_ROUTE = 'ux_live_component';

    public function __construct(
        private readonly ShipmentTrackingRepositoryInterface $shipmentTrackingRepository,
        private readonly ShipmentTrackingManagerInterface $shipmentTrackingManager,
        private readonly OrderPayPalPaymentProviderInterface $orderPayPalPaymentProvider,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'addTrackingFields']);
        $builder->addEventListener(FormEvents::POST_SUBMIT, [$this, 'synchroniseTrackingNumber'], 10);
        $builder->addEventListener(FormEvents::POST_SUBMIT, [$this, 'persistCarrier'], -10);
    }

    public function addTrackingFields(FormEvent $event): void
    {
        $shipment = $event->getData();
        if (!$this->isPaidWithPayPal($shipment)) {
            return;
        }

        /** @var ShipmentInterface $shipment */
        $tracking = $this->shipmentTrackingRepository->findOneByShipment($shipment);

        $event->getForm()->add(self::TRACKING_FIELD_NAME, ShipmentTrackingType::class, [
            'mapped' => false,
            'data' => new ShipmentTrackingData(
                $tracking?->getCarrier(),
                $tracking?->getCarrierNameOther(),
                $shipment->getTracking(),
            ),
        ]);
    }

    public function synchroniseTrackingNumber(FormEvent $event): void
    {
        $shipment = $event->getData();
        $trackingData = $this->getTrackingData($event);

        if (!$shipment instanceof ShipmentInterface || null === $trackingData) {
            return;
        }

        $trackingData->setTrackingNumber($shipment->getTracking());
    }

    public function persistCarrier(FormEvent $event): void
    {
        $shipment = $event->getData();
        $trackingData = $this->getTrackingData($event);

        if (!$shipment instanceof ShipmentInterface || null === $trackingData) {
            return;
        }

        if (!$event->getForm()->isValid() || $this->isLiveComponentRerender()) {
            return;
        }

        if (null === $trackingData->getCarrier()) {
            return;
        }

        $this->shipmentTrackingManager->updateCarrier(
            $shipment,
            $trackingData->getCarrier(),
            $trackingData->getCarrierNameOther(),
        );
    }

    public static function getExtendedTypes(): iterable
    {
        yield ShipmentShipType::class;
    }

    private function getTrackingData(FormEvent $event): ?ShipmentTrackingData
    {
        $form = $event->getForm();
        if (!$form->has(self::TRACKING_FIELD_NAME)) {
            return null;
        }

        $trackingData = $form->get(self::TRACKING_FIELD_NAME)->getData();

        return $trackingData instanceof ShipmentTrackingData ? $trackingData : null;
    }

    private function isPaidWithPayPal(mixed $shipment): bool
    {
        if (!$shipment instanceof ShipmentInterface) {
            return false;
        }

        $order = $shipment->getOrder();
        if (!$order instanceof OrderInterface) {
            return false;
        }

        return null !== $this->orderPayPalPaymentProvider->provide($order);
    }

    private function isLiveComponentRerender(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return null !== $request && self::LIVE_COMPONENT_ROUTE === $request->attributes->get('_route');
    }
}
