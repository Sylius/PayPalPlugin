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

namespace Sylius\PayPalPlugin\PackageTracking\Validator\Constraints;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Repository\ShipmentRepositoryInterface;
use Sylius\PayPalPlugin\PackageTracking\Command\ShipShipmentWithCarrier;
use Sylius\PayPalPlugin\PackageTracking\Model\ShipmentTrackingData;
use Sylius\PayPalPlugin\PackageTracking\Provider\OrderPayPalPaymentProviderInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ShipShipmentCarrierValidator extends ConstraintValidator
{
    /** @param ShipmentRepositoryInterface<ShipmentInterface> $shipmentRepository */
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly OrderPayPalPaymentProviderInterface $orderPayPalPaymentProvider,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ShipShipmentCarrier) {
            throw new UnexpectedTypeException($constraint, ShipShipmentCarrier::class);
        }

        if (!$value instanceof ShipShipmentWithCarrier) {
            throw new UnexpectedValueException($value, ShipShipmentWithCarrier::class);
        }

        $trackingData = new ShipmentTrackingData($value->carrier, $value->carrierNameOther);
        if (null === $trackingData->getCarrier()) {
            return;
        }

        $order = $this->shipmentRepository->find($value->shipmentId)?->getOrder();
        if (!$order instanceof OrderInterface || null === $this->orderPayPalPaymentProvider->provide($order)) {
            return;
        }

        $group = $this->context->getGroup() ?? Constraint::DEFAULT_GROUP;

        $this->context
            ->getValidator()
            ->inContext($this->context)
            ->validate(
                $trackingData,
                new ShipmentTrackingCarrier(groups: [$group]),
                [$group],
            )
        ;
    }
}
