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

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Factory\PurchaseUnitFactoryInterface;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;

final readonly class ChargeVaultedOrderApi implements ChargeVaultedOrderApiInterface
{
    public const STORED_CREDENTIAL = [
        'payment_initiator' => 'MERCHANT',
        'payment_type' => 'RECURRING',
        'usage' => 'SUBSEQUENT',
    ];

    public function __construct(
        private PayPalClientInterface $client,
        private PurchaseUnitFactoryInterface $purchaseUnitFactory,
    ) {
    }

    public function charge(PaymentInterface $payment, string $vaultId, string $referenceId, string $requestId): array
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $payPalOrder = new PayPalOrder(
            order: $order,
            payPalPurchaseUnit: $this->purchaseUnitFactory->create($payment, $referenceId, customId: $requestId),
            intent: PayPalOrder::INTENT_CAPTURE,
            paymentSource: [PayPalPaymentSourceProviderInterface::PAYPAL => [
                'vault_id' => $vaultId,
                'stored_credential' => self::STORED_CREDENTIAL,
            ]],
        );

        return $this->client->post(
            $paymentMethod,
            'v2/checkout/orders',
            $payPalOrder->toArray(),
            ['PayPal-Request-Id' => $requestId],
        );
    }
}
