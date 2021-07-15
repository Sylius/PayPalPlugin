<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;
use Webmozart\Assert\Assert;

final class CreateOrderApi implements CreateOrderApiInterface
{
    const PAYPAL_INTENT_CAPTURE = 'CAPTURE';

    private PayPalClientInterface $client;

    private PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider;

    private PayPalItemDataProviderInterface $payPalItemDataProvider;

    public function __construct(
        PayPalClientInterface $client,
        PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        PayPalItemDataProviderInterface $payPalItemDataProvider
    ) {
        $this->client = $client;
        $this->paymentReferenceNumberProvider = $paymentReferenceNumberProvider;
        $this->payPalItemDataProvider = $payPalItemDataProvider;
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

        $payPalPurchaseUnit = new PayPalPurchaseUnit(
            $referenceId,
            $this->paymentReferenceNumberProvider->provide($payment),
            (string) $order->getCurrencyCode(),
            (int) $payment->getAmount(),
            $order->getShippingTotal(),
            (float) $payPalItemData['total_item_value'],
            (float) $payPalItemData['total_tax'],
            $order->getOrderPromotionTotal(),
            (string) $config['merchant_id'],
            (array) $payPalItemData['items'],
            $order->isShippingRequired(),
            $order->getShippingAddress()
        );

        $payPalOrder = new PayPalOrder($order, $payPalPurchaseUnit, self::PAYPAL_INTENT_CAPTURE);

        return $this->client->post('v2/checkout/orders', $token, $payPalOrder->toArray());
    }
}
