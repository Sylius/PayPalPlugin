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

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Factory\PayPalPurchaseUnitFactory;
use Sylius\PayPalPlugin\Factory\PayPalPurchaseUnitFactoryInterface;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;

final readonly class UpdateOrderApi implements UpdateOrderApiInterface
{
    public function __construct(
        private PayPalClientInterface $client,
        private PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        private PayPalItemDataProviderInterface $payPalItemsDataProvider,
        private ?PayPalPurchaseUnitFactoryInterface $payPalPurchaseUnitFactory = null,
    ) {
        if (null === $this->payPalPurchaseUnitFactory) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $payPalPurchaseUnitFactory to "%s" constructor is deprecated and will be prohibited in 3.0',
                self::class,
            );
        }
    }

    public function update(
        string $token,
        string $orderId,
        PaymentInterface $payment,
        string $referenceId,
        string $merchantId,
    ): array {
        $payPalPurchaseUnit = $this->getPayPalPurchaseUnitFactory()->create($payment, $referenceId, $merchantId);

        return $this->client->patch(
            sprintf('v2/checkout/orders/%s', $orderId),
            $token,
            [
                [
                    'op' => 'replace',
                    'path' => sprintf('/purchase_units/@reference_id==\'%s\'', $referenceId),
                    'value' => $payPalPurchaseUnit->toArray(),
                ],
            ],
        );
    }

    private function getPayPalPurchaseUnitFactory(): PayPalPurchaseUnitFactoryInterface
    {
        return $this->payPalPurchaseUnitFactory ?? new PayPalPurchaseUnitFactory(
            $this->paymentReferenceNumberProvider,
            $this->payPalItemsDataProvider,
        );
    }
}
