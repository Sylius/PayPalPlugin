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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Factory\PurchaseUnitFactoryInterface;

final readonly class UpdateOrderApi implements UpdateOrderApiInterface
{
    public function __construct(
        private PayPalClientInterface $client,
        private PurchaseUnitFactoryInterface $payPalPurchaseUnitFactory,
    ) {
    }

    public function update(
        PaymentMethodInterface $paymentMethod,
        string $orderId,
        PaymentInterface $payment,
        string $referenceId,
        string $merchantId,
    ): array {
        $payPalPurchaseUnit = $this->payPalPurchaseUnitFactory->create($payment, $referenceId, $merchantId);

        return $this->client->patch(
            $paymentMethod,
            sprintf('v2/checkout/orders/%s', $orderId),
            [
                [
                    'op' => 'replace',
                    'path' => sprintf('/purchase_units/@reference_id==\'%s\'', $referenceId),
                    'value' => $payPalPurchaseUnit->toArray(),
                ],
            ],
        );
    }
}
