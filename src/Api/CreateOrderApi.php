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
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;
use Webmozart\Assert\Assert;

final class CreateOrderApi implements CreateOrderApiInterface
{
    /** @var PayPalClientInterface */
    private $client;

    /** @var PaymentReferenceNumberProviderInterface */
    private $paymentReferenceNumberProvider;

    /** @var PayPalItemDataProviderInterface */
    private $payPalItemDataProvider;

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

        $data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $referenceId,
                    'invoice_number' => $this->paymentReferenceNumberProvider->provide($payment),
                    'amount' => [
                        'currency_code' => $order->getCurrencyCode(),
                        'value' => (int) $payment->getAmount() / 100,
                        'breakdown' => [
                            'shipping' => [
                                'currency_code' => $order->getCurrencyCode(),
                                'value' => $order->getShippingTotal() / 100,
                            ],
                            'item_total' => [
                                'currency_code' => $order->getCurrencyCode(),
                                'value' => $payPalItemData['total_item_value'],
                            ],
                            'tax_total' => [
                                'currency_code' => $order->getCurrencyCode(),
                                'value' => $payPalItemData['total_tax'],
                            ],
                            'discount' => [
                                'currency_code' => $order->getCurrencyCode(),
                                'value' => abs($order->getOrderPromotionTotal()) / 100,
                            ],
                        ],
                    ],
                    'payee' => [
                        'merchant_id' => $config['merchant_id'],
                    ],
                    'soft_descriptor' => 'Sylius PayPal Payment',
                    'items' => $payPalItemData['items'],
                ],
            ],
            'application_context' => [
                'shipping_preference' => $order->isShippingRequired() ? 'GET_FROM_FILE' : 'NO_SHIPPING',
            ],
        ];

        $address = $order->getShippingAddress();
        if ($address !== null && $order->isShippingRequired()) {
            $data['purchase_units'][0]['shipping'] = [
                'name' => ['full_name' => $address->getFullName()],
                'address' => [
                    'address_line_1' => $address->getStreet(),
                    'admin_area_2' => $address->getCity(),
                    'postal_code' => $address->getPostcode(),
                    'country_code' => $address->getCountryCode(),
                ],
            ];

            $data['application_context']['shipping_preference'] = 'SET_PROVIDED_ADDRESS';
        }

        return $this->client->post('v2/checkout/orders', $token, $data);
    }
}
