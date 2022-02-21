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
use Sylius\PayPalPlugin\Model\PaymentMethod;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Webmozart\Assert\Assert;

final class CreateOrderApi implements CreateOrderApiInterface
{
    const PAYPAL_INTENT_CAPTURE = 'CAPTURE';
    const PAYPAL_INTENT_AUTHORIZE = 'AUTHORIZE';

    private PayPalClientInterface $client;

    private PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider;

    private PayPalItemDataProviderInterface $payPalItemDataProvider;

    private RequestStack $requestStack;

    public function __construct(
        PayPalClientInterface                   $client,
        PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        PayPalItemDataProviderInterface         $payPalItemDataProvider,
        RequestStack                            $requestStack
    )
    {
        $this->client = $client;
        $this->paymentReferenceNumberProvider = $paymentReferenceNumberProvider;
        $this->payPalItemDataProvider = $payPalItemDataProvider;
        $this->requestStack = $requestStack;
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
        Assert::keyExists($config, 'return_url');
        Assert::keyExists($config, 'cancel_url');

        $payPalPurchaseUnit = new PayPalPurchaseUnit(
            $referenceId,
            $this->paymentReferenceNumberProvider->provide($payment),
            (string)$order->getCurrencyCode(),
            (int)$payment->getAmount(),
            $order->getShippingTotal(),
            (float)$payPalItemData['total_item_value'],
            (float)$payPalItemData['total_tax'],
            $order->getOrderPromotionTotal(),
            (string)$config['merchant_id'],
            (array)$payPalItemData['items'],
            $order->isShippingRequired(),
            $order->getShippingAddress()
        );

        $paymentMethod = new PaymentMethod(
            PaymentMethod::IMMEDIATE_PAYMENT
        );

        $payPalOrder = new PayPalOrder(
            $order,
            $payPalPurchaseUnit,
            $paymentMethod,
            self::PAYPAL_INTENT_CAPTURE,
            $this->_getApplicationContext($config, $order)
        );

        return $this->client->post('v2/checkout/orders', $token, $payPalOrder->toArray());
    }

    private function _getApplicationContext(array $config, OrderInterface $order): array
    {
        Assert::keyExists($config, 'return_url');
        Assert::keyExists($config, 'cancel_url');

        $baseUrl = $this->requestStack->getCurrentRequest()->headers->get('origin');
        return str_replace(
            ['@baseUrl', '@orderTokenValue'],
            [$baseUrl, $order->getTokenValue()],
            ['return_url' => $config['return_url'], 'cancel_url' => $config['cancel_url']]
        );
    }
}
