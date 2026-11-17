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

namespace Tests\Sylius\PayPalPlugin\Unit\Checker;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Checker\PaymentRefundChecker;

final class PaymentRefundCheckerTest extends TestCase
{
    public function test_it_blocks_the_refund_of_a_paypal_payment_paypal_has_not_refunded(): void
    {
        self::assertFalse((new PaymentRefundChecker())->canBeRefunded($this->payment('sylius_paypal', ['paypal_order_id' => 'PAYPAL_ORDER_ID'])));
    }

    public function test_it_allows_the_refund_of_a_paypal_payment_paypal_has_refunded(): void
    {
        self::assertTrue((new PaymentRefundChecker())->canBeRefunded($this->payment('sylius_paypal', [
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'refunded' => true,
            'refund_id' => 'REFUND_ID',
        ])));
    }

    public function test_it_allows_the_refund_of_a_paypal_payment_never_sent_to_paypal(): void
    {
        self::assertTrue((new PaymentRefundChecker())->canBeRefunded($this->payment('sylius_paypal', [])));
    }

    public function test_it_allows_the_refund_of_a_payment_of_another_gateway(): void
    {
        self::assertTrue((new PaymentRefundChecker())->canBeRefunded($this->payment('offline', ['paypal_order_id' => 'PAYPAL_ORDER_ID'])));
    }

    public function test_it_allows_the_refund_of_a_payment_without_a_method(): void
    {
        self::assertTrue((new PaymentRefundChecker())->canBeRefunded(new Payment()));
    }

    /** @param array<string, mixed> $details */
    private function payment(string $factoryName, array $details): Payment
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName($factoryName);
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);
        $payment = new Payment();
        $payment->setMethod($paymentMethod);
        $payment->setDetails($details);

        return $payment;
    }
}
