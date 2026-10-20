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

namespace Tests\Sylius\PayPalPlugin\Unit\Api;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\ChargeVaultedOrderApi;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Factory\PurchaseUnitFactoryInterface;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;

final class ChargeVaultedOrderApiTest extends TestCase
{
    public function test_it_creates_an_order_paid_with_the_vaulted_wallet_as_a_recurring_merchant_initiated_charge(): void
    {
        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getOrder')->willReturn($this->createStub(OrderInterface::class));

        $purchaseUnit = $this->createStub(PayPalPurchaseUnit::class);
        $purchaseUnit->method('toArray')->willReturn(['reference_id' => 'REFERENCE_ID']);

        $purchaseUnitFactory = $this->createMock(PurchaseUnitFactoryInterface::class);
        $purchaseUnitFactory
            ->expects(self::once())
            ->method('create')
            ->with($payment, 'REFERENCE_ID', null, true, 'PAYMENT_REQUEST_HASH')
            ->willReturn($purchaseUnit)
        ;

        $client = $this->createMock(PayPalClientInterface::class);
        $client
            ->expects(self::once())
            ->method('post')
            ->with(
                $paymentMethod,
                'v2/checkout/orders',
                [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [['reference_id' => 'REFERENCE_ID']],
                    'payment_source' => ['paypal' => [
                        'vault_id' => 'VAULT_ID',
                        'stored_credential' => [
                            'payment_initiator' => 'MERCHANT',
                            'payment_type' => 'RECURRING',
                            'usage' => 'SUBSEQUENT',
                        ],
                    ]],
                ],
                ['PayPal-Request-Id' => 'PAYMENT_REQUEST_HASH'],
            )
            ->willReturn(['id' => 'PAYPAL_ORDER_ID', 'status' => 'COMPLETED'])
        ;

        self::assertSame(
            ['id' => 'PAYPAL_ORDER_ID', 'status' => 'COMPLETED'],
            (new ChargeVaultedOrderApi($client, $purchaseUnitFactory))->charge($payment, 'VAULT_ID', 'REFERENCE_ID', 'PAYMENT_REQUEST_HASH'),
        );
    }
}
