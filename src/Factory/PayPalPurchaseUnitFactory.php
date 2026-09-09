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

namespace Sylius\PayPalPlugin\Factory;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;
use Webmozart\Assert\Assert;

final readonly class PayPalPurchaseUnitFactory implements PayPalPurchaseUnitFactoryInterface
{
    public function __construct(
        private PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        private PayPalItemDataProviderInterface $payPalItemDataProvider,
    ) {
    }

    public function create(
        PaymentInterface $payment,
        string $referenceId,
        ?string $merchantId = null,
    ): PayPalPurchaseUnit {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $payPalItemData = $this->payPalItemDataProvider->provide($order);

        $shippingDiscount = $order->getAdjustmentsTotalRecursively(
            AdjustmentInterface::ORDER_SHIPPING_PROMOTION_ADJUSTMENT,
        );

        return new PayPalPurchaseUnit(
            $referenceId,
            $this->paymentReferenceNumberProvider->provide($payment),
            (string) $order->getCurrencyCode(),
            (int) $payment->getAmount(),
            $order->getShippingTotal() - $shippingDiscount,
            (float) $payPalItemData['total_item_value'],
            (float) $payPalItemData['total_tax'],
            $order->getOrderPromotionTotal(),
            $merchantId ?? $this->getMerchantId($payment),
            (array) $payPalItemData['items'],
            $order->isShippingRequired(),
            $order->getShippingAddress(),
            shippingDiscountValue: $shippingDiscount,
        );
    }

    private function getMerchantId(PaymentInterface $payment): string
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        $config = $gatewayConfig->getConfig();

        Assert::keyExists($config, 'merchant_id');
        Assert::keyExists($config, 'sylius_merchant_id');

        return (string) $config['merchant_id'];
    }
}
