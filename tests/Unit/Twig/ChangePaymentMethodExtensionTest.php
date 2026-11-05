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

namespace Tests\Sylius\PayPalPlugin\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\PayPalPlugin\Twig\ChangePaymentMethodExtension;

final class ChangePaymentMethodExtensionTest extends TestCase
{
    public function test_it_lets_the_buyer_change_the_payment_method_of_an_order_awaiting_a_payment_in_an_allowed_state(): void
    {
        $order = $this->order(OrderPaymentStates::STATE_AWAITING_PAYMENT, PaymentInterface::STATE_FAILED, PaymentInterface::STATE_NEW);

        self::assertTrue((new ChangePaymentMethodExtension(['new', 'cart']))->canChangePaymentMethod($order));
    }

    public function test_it_does_not_let_the_buyer_change_the_payment_method_while_the_payment_is_processing(): void
    {
        $order = $this->order(OrderPaymentStates::STATE_AWAITING_PAYMENT, PaymentInterface::STATE_PROCESSING);

        self::assertFalse((new ChangePaymentMethodExtension(['new', 'cart']))->canChangePaymentMethod($order));
    }

    public function test_it_follows_the_payment_states_the_store_allows(): void
    {
        $order = $this->order(OrderPaymentStates::STATE_AWAITING_PAYMENT, PaymentInterface::STATE_PROCESSING);

        self::assertTrue((new ChangePaymentMethodExtension(['new', 'cart', 'processing']))->canChangePaymentMethod($order));
    }

    public function test_it_does_not_let_the_buyer_change_the_payment_method_of_an_order_no_longer_awaiting_payment(): void
    {
        $order = $this->order(OrderPaymentStates::STATE_PAID, PaymentInterface::STATE_COMPLETED, PaymentInterface::STATE_CART);

        self::assertFalse((new ChangePaymentMethodExtension(['new', 'cart']))->canChangePaymentMethod($order));
    }

    private function order(string $paymentState, string ...$paymentStates): OrderInterface
    {
        $order = new Order();
        $order->setPaymentState($paymentState);
        foreach ($paymentStates as $state) {
            $payment = new Payment();
            $payment->setState($state);
            $order->addPayment($payment);
        }

        return $order;
    }
}
