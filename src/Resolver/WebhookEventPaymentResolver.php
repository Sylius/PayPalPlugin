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

namespace Sylius\PayPalPlugin\Resolver;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Model\PayPalWebhookEventNotification;
use Sylius\PayPalPlugin\Repository\Query\SettleablePaypalPaymentQueryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class WebhookEventPaymentResolver implements WebhookEventPaymentResolverInterface
{
    private const RESOURCE_TYPE_CAPTURE = 'capture';

    private const RESOURCE_TYPE_CHECKOUT_ORDER = 'checkout-order';

    private const RESOURCE_TYPE_REFUND = 'refund';

    public function __construct(
        private SettleablePaypalPaymentQueryInterface $paypalPaymentQuery,
    ) {
    }

    public function resolve(PayPalWebhookEventNotification $notification): ?PaymentInterface
    {
        $resource = $notification->resource();
        $resourceType = $notification->resourceType();

        foreach ($this->customIds($resource) as $customId) {
            try {
                return $this->paypalPaymentQuery->getByPaymentRequestHash($customId);
            } catch (PaymentNotFoundException) {
            }
        }

        $orderId = $this->orderId($resourceType, $notification->resourceId(), $resource);
        if (null !== $orderId) {
            try {
                return $this->paypalPaymentQuery->getForSettlementByOrderId($orderId);
            } catch (PaymentNotFoundException) {
            }
        }

        $captureId = $this->captureId($resourceType, $notification->resourceId(), $resource);
        if (null !== $captureId) {
            try {
                return $this->paypalPaymentQuery->getForSettlementByCaptureId($captureId);
            } catch (PaymentNotFoundException) {
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $resource
     *
     * @return list<string>
     */
    private function customIds(array $resource): array
    {
        $customIds = [$resource['custom_id'] ?? null];

        $purchaseUnits = $resource['purchase_units'] ?? [];
        foreach (is_array($purchaseUnits) ? $purchaseUnits : [] as $purchaseUnit) {
            $customIds[] = is_array($purchaseUnit) ? ($purchaseUnit['custom_id'] ?? null) : null;
        }

        return array_values(array_filter($customIds, static fn (mixed $customId): bool => is_string($customId) && Uuid::isValid($customId)));
    }

    /** @param array<string, mixed> $resource */
    private function orderId(string $resourceType, string $resourceId, array $resource): ?string
    {
        if (self::RESOURCE_TYPE_CHECKOUT_ORDER === $resourceType) {
            return $resourceId;
        }

        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        return is_string($orderId) && '' !== $orderId ? $orderId : null;
    }

    /** @param array<string, mixed> $resource */
    private function captureId(string $resourceType, string $resourceId, array $resource): ?string
    {
        if (self::RESOURCE_TYPE_CAPTURE === $resourceType) {
            return $resourceId;
        }

        if (self::RESOURCE_TYPE_REFUND !== $resourceType) {
            return null;
        }

        $links = $resource['links'] ?? [];
        foreach (is_array($links) ? $links : [] as $link) {
            if (
                is_array($link) &&
                'up' === ($link['rel'] ?? null) &&
                is_string($link['href'] ?? null) &&
                1 === preg_match('#/v2/payments/captures/([^/?]+)$#', (string) parse_url($link['href'], \PHP_URL_PATH), $matches)
            ) {
                return $matches[1];
            }
        }

        return null;
    }
}
