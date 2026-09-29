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

namespace Tests\Sylius\PayPalPlugin\Unit\Processor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\PayPalPlugin\Processor\ReplacementOrderPaymentProcessor;

final class ReplacementOrderPaymentProcessorTest extends TestCase
{
    public function test_it_processes_the_order_payments_when_nothing_replaced_the_cancelled_one(): void
    {
        $order = $this->createStub(OrderInterface::class);
        $orderPaymentProcessor = $this->createMock(OrderProcessorInterface::class);

        $orderPaymentProcessor->expects(self::once())->method('process')->with($order);

        (new ReplacementOrderPaymentProcessor($orderPaymentProcessor))->process($order);
    }

    /** @return iterable<string, array{string}> */
    public static function replacementStates(): iterable
    {
        yield 'new' => [PaymentInterface::STATE_NEW];
        yield 'cart' => [PaymentInterface::STATE_CART];
    }

    #[DataProvider('replacementStates')]
    public function test_it_does_not_add_a_second_payment_when_one_already_replaced_the_cancelled_one(string $state): void
    {
        $order = $this->createStub(OrderInterface::class);
        $order->method('getLastPayment')->willReturnCallback(
            fn (?string $lastPaymentState = null): ?PaymentInterface => $state === $lastPaymentState ? $this->createStub(PaymentInterface::class) : null,
        );
        $orderPaymentProcessor = $this->createMock(OrderProcessorInterface::class);

        $orderPaymentProcessor->expects(self::never())->method('process');

        (new ReplacementOrderPaymentProcessor($orderPaymentProcessor))->process($order);
    }
}
