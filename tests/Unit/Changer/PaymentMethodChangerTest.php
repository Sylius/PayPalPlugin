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

namespace Tests\Sylius\PayPalPlugin\Unit\Changer;

use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ApiBundle\Changer\PaymentMethodChangerInterface;
use Sylius\Component\Core\Model\Order;
use Sylius\PayPalPlugin\Changer\PaymentMethodChanger;
use Sylius\PayPalPlugin\Remover\PayPalPaymentDetailsRemoverInterface;

final class PaymentMethodChangerTest extends TestCase
{
    public function test_it_removes_paypal_payment_details_after_the_payment_method_is_changed(): void
    {
        $order = new Order();
        $decoratedPaymentMethodChanger = $this->createMock(PaymentMethodChangerInterface::class);
        $remover = $this->createMock(PayPalPaymentDetailsRemoverInterface::class);

        $decoratedPaymentMethodChanger->expects(self::once())->method('changePaymentMethod')->with('BANK_TRANSFER', 7, $order)->willReturn($order);
        $remover->expects(self::once())->method('removeFromOrder')->with($order);

        self::assertSame($order, (new PaymentMethodChanger($decoratedPaymentMethodChanger, $remover))->changePaymentMethod('BANK_TRANSFER', 7, $order));
    }

    public function test_it_keeps_the_paypal_payment_details_when_the_payment_method_cannot_be_changed(): void
    {
        $decoratedPaymentMethodChanger = $this->createMock(PaymentMethodChangerInterface::class);
        $remover = $this->createMock(PayPalPaymentDetailsRemoverInterface::class);

        $decoratedPaymentMethodChanger->method('changePaymentMethod')->willThrowException(new \InvalidArgumentException());
        $remover->expects(self::never())->method('removeFromOrder');

        $this->expectException(\InvalidArgumentException::class);

        (new PaymentMethodChanger($decoratedPaymentMethodChanger, $remover))->changePaymentMethod('BANK_TRANSFER', 7, new Order());
    }
}
