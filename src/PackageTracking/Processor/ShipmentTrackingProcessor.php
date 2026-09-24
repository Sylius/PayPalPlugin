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

namespace Sylius\PayPalPlugin\PackageTracking\Processor;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface as BaseShipmentInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiErrorException;
use Sylius\PayPalPlugin\PackageTracking\Api\AddTrackingApiInterface;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTrackingInterface;
use Sylius\PayPalPlugin\PackageTracking\Exception\ShipmentTrackingNotReadyException;
use Sylius\PayPalPlugin\PackageTracking\Provider\CarrierProviderInterface;
use Sylius\PayPalPlugin\PackageTracking\Provider\OrderPayPalPaymentProviderInterface;
use Sylius\PayPalPlugin\PackageTracking\Provider\ShipmentTrackingItemsProviderInterface;
use Sylius\PayPalPlugin\PackageTracking\Repository\ShipmentTrackingRepositoryInterface;

final readonly class ShipmentTrackingProcessor implements ShipmentTrackingProcessorInterface
{
    private const ELIGIBLE_ORDER_STATUSES = ['COMPLETED', 'PARTIALLY_REFUNDED', 'PENDING'];

    public function __construct(
        private ShipmentTrackingRepositoryInterface $shipmentTrackingRepository,
        private OrderPayPalPaymentProviderInterface $orderPayPalPaymentProvider,
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private OrderDetailsApiInterface $orderDetailsApi,
        private AddTrackingApiInterface $addTrackingApi,
        private ShipmentTrackingItemsProviderInterface $itemsProvider,
        private CarrierProviderInterface $carrierProvider,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function process(ShipmentInterface $shipment): void
    {
        $tracking = $this->shipmentTrackingRepository->findOneByShipment($shipment);
        if (null === $tracking) {
            return;
        }

        /** @var OrderInterface|null $order */
        $order = $shipment->getOrder();
        if (null === $order) {
            return;
        }

        $payment = $this->orderPayPalPaymentProvider->provide($order);
        if (null === $payment) {
            return;
        }

        $state = (string) $shipment->getState();
        if (BaseShipmentInterface::STATE_SHIPPED !== $state) {
            throw new ShipmentTrackingNotReadyException($shipment->getId(), $state);
        }

        try {
            $this->sendTracking($shipment, $tracking, $payment);
        } catch (\Throwable $exception) {
            $tracking->markAsFailed($this->formatError($exception));
            $this->entityManager->flush();

            $this->logger->error(
                sprintf('Failed to send PayPal tracking for shipment #%s: %s', (string) $shipment->getId(), $exception->getMessage()),
                ['exception' => $exception],
            );

            throw $exception;
        }

        $this->entityManager->flush();
    }

    /**
     * @throws PayPalApiErrorException
     */
    private function sendTracking(
        ShipmentInterface $shipment,
        ShipmentTrackingInterface $tracking,
        PaymentInterface $payment,
    ): void {
        $trackingNumber = $shipment->getTracking();
        if (null === $trackingNumber || '' === $trackingNumber) {
            $tracking->markAsFailed('Shipment has no tracking number.');

            return;
        }

        $carrier = $tracking->getCarrier();
        if (null === $carrier || '' === $carrier) {
            $tracking->markAsFailed('No carrier selected for the shipment.');

            return;
        }

        $details = $payment->getDetails();
        $payPalOrderId = (string) ($details['paypal_order_id'] ?? '');
        if ('' === $payPalOrderId) {
            $tracking->markAsFailed('Payment details do not carry a PayPal order id.');

            return;
        }

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $token = $this->authorizeClientApi->authorize($paymentMethod);

        $orderDetails = $this->orderDetailsApi->get($token, $payPalOrderId);

        if (!isset($orderDetails['status'])) {
            throw new PayPalApiErrorException(sprintf('GET v2/checkout/orders/%s', $payPalOrderId), $orderDetails);
        }

        $status = (string) $orderDetails['status'];
        if (!in_array($status, self::ELIGIBLE_ORDER_STATUSES, true)) {
            $tracking->markAsFailed(sprintf('PayPal order status "%s" is not eligible for tracking.', $status));

            return;
        }

        if ([] === ($orderDetails['purchase_units'][0]['items'] ?? [])) {
            $tracking->markAsFailed('PayPal order has no items, so it is not eligible for tracking.');

            return;
        }

        $captureId = $this->resolveCaptureId($details, $orderDetails);
        if (null === $captureId) {
            $tracking->markAsFailed('Could not resolve the PayPal capture id from the payment details.');

            return;
        }

        $body = [
            'capture_id' => $captureId,
            'tracking_number' => $trackingNumber,
            'carrier' => $carrier,
            'notify_payer' => true,
            'items' => $this->itemsProvider->provide($shipment),
        ];

        if ($this->carrierProvider->isOther($carrier)) {
            $body['carrier_name_other'] = (string) $tracking->getCarrierNameOther();
        }

        $response = $this->addTrackingApi->add($token, $payPalOrderId, $body);

        if (!isset($response['id'])) {
            throw new PayPalApiErrorException(sprintf('POST v2/checkout/orders/%s/track', $payPalOrderId), $response);
        }

        $tracking->markAsSynced($this->extractTrackerId($response, $trackingNumber));
    }

    /**
     * @param array<string, mixed> $paymentDetails
     * @param array<string, mixed> $orderDetails
     */
    private function resolveCaptureId(array $paymentDetails, array $orderDetails): ?string
    {
        if (isset($paymentDetails['transaction_id']) && '' !== $paymentDetails['transaction_id']) {
            return (string) $paymentDetails['transaction_id'];
        }

        $captureId = $orderDetails['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;

        return null !== $captureId ? (string) $captureId : null;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractTrackerId(array $response, string $trackingNumber): ?string
    {
        $fallback = null;

        foreach ($response['purchase_units'] ?? [] as $purchaseUnit) {
            foreach ($purchaseUnit['shipping']['trackers'] ?? [] as $tracker) {
                $trackerId = isset($tracker['id']) ? (string) $tracker['id'] : null;
                if (null === $trackerId) {
                    continue;
                }

                if (str_contains($trackerId, $trackingNumber)) {
                    return $trackerId;
                }

                $fallback = $trackerId;
            }
        }

        return $fallback;
    }

    private function formatError(\Throwable $exception): string
    {
        return sprintf('%s: %s', $exception::class, $exception->getMessage());
    }
}
