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
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Checker\PaymentCompletionChecker;

final class PaymentCompletionCheckerTest extends TestCase
{
    public function test_it_blocks_completing_an_authorized_paypal_payment_paypal_has_not_captured(): void
    {
        self::assertFalse((new PaymentCompletionChecker())->canBeCompleted($this->payment('sylius_paypal', PaymentInterface::STATE_AUTHORIZED, [
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'authorization_id' => 'AUTHORIZATION_ID',
            'authorization_status' => 'CREATED',
        ])));
    }

    public function test_it_allows_completing_an_authorized_paypal_payment_paypal_has_captured(): void
    {
        self::assertTrue((new PaymentCompletionChecker())->canBeCompleted($this->payment('sylius_paypal', PaymentInterface::STATE_AUTHORIZED, [
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'authorization_id' => 'AUTHORIZATION_ID',
            'authorization_status' => 'CAPTURED',
        ])));
    }

    public function test_it_allows_completing_a_paypal_payment_that_is_not_authorized(): void
    {
        $checker = new PaymentCompletionChecker();

        self::assertTrue($checker->canBeCompleted($this->payment('sylius_paypal', PaymentInterface::STATE_NEW, ['paypal_order_id' => 'PAYPAL_ORDER_ID'])));
        self::assertTrue($checker->canBeCompleted($this->payment('sylius_paypal', PaymentInterface::STATE_PROCESSING, ['paypal_order_id' => 'PAYPAL_ORDER_ID'])));
    }

    public function test_it_allows_completing_an_authorized_payment_of_another_gateway(): void
    {
        self::assertTrue((new PaymentCompletionChecker())->canBeCompleted($this->payment('offline', PaymentInterface::STATE_AUTHORIZED, [])));
    }

    public function test_it_allows_completing_an_authorized_payment_without_a_method(): void
    {
        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_AUTHORIZED);

        self::assertTrue((new PaymentCompletionChecker())->canBeCompleted($payment));
    }

    /** @param array<string, mixed> $details */
    private function payment(string $factoryName, string $state, array $details): Payment
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName($factoryName);
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);
        $payment = new Payment();
        $payment->setMethod($paymentMethod);
        $payment->setState($state);
        $payment->setDetails($details);

        return $payment;
    }
}
