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
        private ?UrlGeneratorInterface $router = null,
    ) {
        if (null === $this->router) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $router to "%s" constructor is deprecated and will be prohibited in 3.0',
                self::class,
            );
        }
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

        $payPalPurchaseUnit = new PayPalPurchaseUnit(
            $referenceId,
            $this->paymentReferenceNumberProvider->provide($payment),
            (string) $order->getCurrencyCode(),
            (int) $payment->getAmount(),
            $order->getShippingTotal() - $shippingDiscount,
            (float) $payPalItemData['total_item_value'],
            (float) $payPalItemData['total_tax'],
            $order->getOrderPromotionTotal(),
            (string) $config['merchant_id'],
            (array) $payPalItemData['items'],
            $order->isShippingRequired(),
            $order->getShippingAddress(),
            shippingDiscountValue: $shippingDiscount,
        );

        $payerReturnUrl = $this->router?->generate(
            'sylius_shop_checkout_complete',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $payPalOrder = new PayPalOrder(
            $order,
            $payPalPurchaseUnit,
            self::PAYPAL_INTENT_CAPTURE,
            $payerReturnUrl,
            $payerReturnUrl,
        );

        return $this->client->post('v2/checkout/orders', $token, $payPalOrder->toArray());
    }
}
