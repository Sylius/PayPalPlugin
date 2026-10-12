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
use Sylius\PayPalPlugin\PackageTracking\Model\ShipmentTrackingData;
use Sylius\PayPalPlugin\PackageTracking\Provider\OrderPayPalPaymentProviderInterface;
use Sylius\PayPalPlugin\PackageTracking\Repository\ShipmentTrackingRepositoryInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class ShipmentShipTypeExtension extends AbstractTypeExtension
{
    public const TRACKING_FIELD_NAME = 'paypal_tracking';

    public function __construct(
        private readonly ShipmentTrackingRepositoryInterface $shipmentTrackingRepository,
        private readonly OrderPayPalPaymentProviderInterface $orderPayPalPaymentProvider,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'addTrackingFields']);
        $builder->addEventListener(FormEvents::POST_SUBMIT, [$this, 'synchroniseTrackingCode'], 10);
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
            'error_bubbling' => false,
            'data' => new ShipmentTrackingData($tracking?->getCarrier(), $tracking?->getCarrierNameOther()),
        ]);
    }

    public function synchroniseTrackingCode(FormEvent $event): void
    {
        $shipment = $event->getData();
        $form = $event->getForm();
        if (!$shipment instanceof ShipmentInterface || !$form->has(self::TRACKING_FIELD_NAME)) {
            return;
        }

        $trackingData = $form->get(self::TRACKING_FIELD_NAME)->getData();
        if ($trackingData instanceof ShipmentTrackingData) {
            $trackingData->setTrackingCode($shipment->getTracking());
        }
    }

    public static function getExtendedTypes(): iterable
    {
        yield ShipmentShipType::class;
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
}
