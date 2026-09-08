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

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final readonly class CreateOrderApi implements CreateOrderApiInterface
{
    public const PAYPAL_INTENT_CAPTURE = 'CAPTURE';

    public function __construct(
        private PayPalClientInterface $client,
        private PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        private PayPalItemDataProviderInterface $payPalItemDataProvider,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function create(string $token, PaymentInterface $payment, string $referenceId): array
    {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        $payPalItemData = $this->payPalItemDataProvider->provide($order);

        $config = $gatewayConfig->getConfig();

        Assert::keyExists($config, 'merchant_id');
        Assert::keyExists($config, 'sylius_merchant_id');

        $shippingDiscount = $order->getAdjustmentsTotalRecursively(
            AdjustmentInterface::ORDER_SHIPPING_PROMOTION_ADJUSTMENT,
        );

        $paymentReferenceNumber = $this->paymentReferenceNumberProvider->provide($payment);

        $payPalPurchaseUnit = new PayPalPurchaseUnit(
            referenceId: $referenceId,
            invoiceNumber: $paymentReferenceNumber . '-' . $referenceId,
            currencyCode: (string) $order->getCurrencyCode(),
            totalAmount: (int) $payment->getAmount(),
            shippingValue: $order->getShippingTotal() - $shippingDiscount,
            itemTotalValue: (float) $payPalItemData['total_item_value'],
            taxTotalValue: (float) $payPalItemData['total_tax'],
            discountValue: $order->getOrderPromotionTotal(),
            merchantId: (string) $config['merchant_id'],
            items: (array) $payPalItemData['items'],
            shippingRequired: $order->isShippingRequired(),
            shippingAddress: $order->getShippingAddress(),
            shippingDiscountValue: $shippingDiscount,
            customId: $paymentReferenceNumber,
        );

        $paymentPageUrl = $this->providePaymentPageUrl($order, $payment);

        $payPalOrder = new PayPalOrder(
            $order,
            $payPalPurchaseUnit,
            self::PAYPAL_INTENT_CAPTURE,
            $this->provideBrandName($order),
            $this->provideLocaleCode($order),
            $paymentPageUrl,
            $paymentPageUrl,
        );

        return $this->client->post('v2/checkout/orders', $token, $payPalOrder->toArray());
    }

    private function provideBrandName(OrderInterface $order): string
    {
        /** @var ChannelInterface|null $channel */
        $channel = $order->getChannel();

        return (string) $channel?->getName();
    }

    private function provideLocaleCode(OrderInterface $order): string
    {
        // PayPal expects a BCP 47 locale (e.g. "en-US"), while Sylius stores it as "en_US".
        return str_replace('_', '-', (string) $order->getLocaleCode());
    }

    private function providePaymentPageUrl(OrderInterface $order, PaymentInterface $payment): string
    {
        return $this->urlGenerator->generate(
            'sylius_paypal_shop_pay_with_paypal_form',
            [
                'orderToken' => $order->getTokenValue(),
                'paymentId' => $payment->getId(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
