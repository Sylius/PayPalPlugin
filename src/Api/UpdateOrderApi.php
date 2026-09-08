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

namespace Sylius\PayPalPlugin\Api;

use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;

final readonly class UpdateOrderApi implements UpdateOrderApiInterface
{
    public function __construct(
        private PayPalClientInterface $client,
        private PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        private PayPalItemDataProviderInterface $payPalItemsDataProvider,
    ) {
    }

    public function update(
        string $token,
        string $orderId,
        PaymentInterface $payment,
        string $referenceId,
        string $merchantId,
    ): array {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $payPalItemData = $this->payPalItemsDataProvider->provide($order);

        $shippingDiscount = $order->getAdjustmentsTotalRecursively(
            AdjustmentInterface::ORDER_SHIPPING_PROMOTION_ADJUSTMENT,
        );

        $paymentReferenceNumber = $this->paymentReferenceNumberProvider->provide($payment);

        $data = new PayPalPurchaseUnit(
            referenceId: $referenceId,
            invoiceNumber: $paymentReferenceNumber,
            currencyCode: (string) $order->getCurrencyCode(),
            totalAmount: (int) $payment->getAmount(),
            shippingValue: $order->getShippingTotal() - $shippingDiscount,
            itemTotalValue: (float) $payPalItemData['total_item_value'],
            taxTotalValue: (float) $payPalItemData['total_tax'],
            discountValue: $order->getOrderPromotionTotal(),
            merchantId: $merchantId,
            items: (array) $payPalItemData['items'],
            shippingRequired: $order->isShippingRequired(),
            shippingAddress: $order->getShippingAddress(),
            shippingDiscountValue: $shippingDiscount,
            customId: $paymentReferenceNumber,
        );

        return $this->client->patch(
            sprintf('v2/checkout/orders/%s', $orderId),
            $token,
            [
                [
                    'op' => 'replace',
                    'path' => sprintf('/purchase_units/@reference_id==\'%s\'', $referenceId),
                    'value' => $data->toArray(),
                ],
            ],
        );
    }
}
