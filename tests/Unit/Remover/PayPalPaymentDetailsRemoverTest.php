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

namespace Tests\Sylius\PayPalPlugin\Unit\Remover;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Remover\PayPalPaymentDetailsRemover;

final class PayPalPaymentDetailsRemoverTest extends TestCase
{
    public function test_it_removes_paypal_details_from_a_cart_payment_switched_to_another_method(): void
    {
        $payment = $this->payment('offline', PaymentInterface::STATE_CART, [
            'status' => 'CAPTURED',
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'reference_id' => 'REFERENCE_ID',
            'payment_amount' => 5304,
            'payment_source' => 'paypal',
        ]);

        (new PayPalPaymentDetailsRemover())->removeFromOrder($this->order($payment));

        self::assertSame([], $payment->getDetails());
    }

    public function test_it_removes_paypal_details_from_a_new_payment_switched_to_another_method(): void
    {
        $payment = $this->payment('offline', PaymentInterface::STATE_NEW, ['paypal_order_id' => 'PAYPAL_ORDER_ID']);

        (new PayPalPaymentDetailsRemover())->removeFromOrder($this->order($payment));

        self::assertSame([], $payment->getDetails());
    }

    public function test_it_removes_a_paypal_payment_source_written_before_the_paypal_order_was_created(): void
    {
        $payment = $this->payment('offline', PaymentInterface::STATE_CART, ['payment_source' => 'venmo']);

        (new PayPalPaymentDetailsRemover())->removeFromOrder($this->order($payment));

        self::assertSame([], $payment->getDetails());
    }

    public function test_it_keeps_the_details_of_a_paypal_payment(): void
    {
        $payment = $this->payment('sylius_paypal', PaymentInterface::STATE_CART, ['paypal_order_id' => 'PAYPAL_ORDER_ID']);

        (new PayPalPaymentDetailsRemover())->removeFromOrder($this->order($payment));

        self::assertSame(['paypal_order_id' => 'PAYPAL_ORDER_ID'], $payment->getDetails());
    }

    public function test_it_keeps_the_details_of_another_gateway(): void
    {
        $payment = $this->payment('offline', PaymentInterface::STATE_NEW, ['paid' => false]);

        (new PayPalPaymentDetailsRemover())->removeFromOrder($this->order($payment));

        self::assertSame(['paid' => false], $payment->getDetails());
    }

    public function test_it_keeps_the_paypal_details_of_a_payment_past_the_new_state(): void
    {
        $remover = new PayPalPaymentDetailsRemover();

        foreach ([PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_AUTHORIZED, PaymentInterface::STATE_COMPLETED, PaymentInterface::STATE_CANCELLED] as $state) {
            $payment = $this->payment('offline', $state, ['paypal_order_id' => 'PAYPAL_ORDER_ID']);

            $remover->removeFromOrder($this->order($payment));

            self::assertSame(['paypal_order_id' => 'PAYPAL_ORDER_ID'], $payment->getDetails(), $state);
        }
    }

    public function test_it_removes_paypal_details_from_a_payment_without_a_method(): void
    {
        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_CART);
        $payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID']);

        (new PayPalPaymentDetailsRemover())->removeFromOrder($this->order($payment));

        self::assertSame([], $payment->getDetails());
    }

    public function test_it_removes_paypal_details_only_from_the_payments_switched_to_another_method(): void
    {
        $cancelledPayment = $this->payment('sylius_paypal', PaymentInterface::STATE_CANCELLED, ['paypal_order_id' => 'CANCELLED_PAYPAL_ORDER_ID']);
        $payment = $this->payment('offline', PaymentInterface::STATE_NEW, ['paypal_order_id' => 'PAYPAL_ORDER_ID']);

        (new PayPalPaymentDetailsRemover())->removeFromOrder($this->order($cancelledPayment, $payment));

        self::assertSame(['paypal_order_id' => 'CANCELLED_PAYPAL_ORDER_ID'], $cancelledPayment->getDetails());
        self::assertSame([], $payment->getDetails());
    }

    private function order(PaymentInterface ...$payments): Order
    {
        $order = new Order();
        foreach ($payments as $payment) {
            $order->addPayment($payment);
        }

        return $order;
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
