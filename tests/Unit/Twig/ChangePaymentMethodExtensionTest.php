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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\PayPalPlugin\Twig\ChangePaymentMethodExtension;

final class ChangePaymentMethodExtensionTest extends TestCase
{
    /** @return iterable<string, array{string, list<string>, bool}> */
    public static function orders(): iterable
    {
        yield 'a new payment after a failed one' => [OrderPaymentStates::STATE_AWAITING_PAYMENT, [PaymentInterface::STATE_FAILED, PaymentInterface::STATE_NEW], true];
        yield 'a processing payment' => [OrderPaymentStates::STATE_AWAITING_PAYMENT, [PaymentInterface::STATE_PROCESSING], false];
        yield 'a processing payment next to a new one' => [OrderPaymentStates::STATE_AWAITING_PAYMENT, [PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_NEW], true];
        yield 'an order no longer awaiting payment' => [OrderPaymentStates::STATE_PAID, [PaymentInterface::STATE_COMPLETED, PaymentInterface::STATE_CART], false];
    }

    /** @param list<string> $paymentStates */
    #[DataProvider('orders')]
    public function test_it_tells_whether_the_buyer_can_change_the_payment_method(string $orderPaymentState, array $paymentStates, bool $expected): void
    {
        $extension = new ChangePaymentMethodExtension([PaymentInterface::STATE_NEW, PaymentInterface::STATE_CART]);

        self::assertSame($expected, $extension->canChangePaymentMethod($this->order($orderPaymentState, $paymentStates)));
    }

    /** @param list<string> $paymentStates */
    private function order(string $paymentState, array $paymentStates): OrderInterface
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
