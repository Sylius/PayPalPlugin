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

namespace Tests\Sylius\PayPalPlugin\Unit\EventListener;

use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;
use Sylius\PayPalPlugin\EventListener\RemovePayPalPaymentDetailsListener;
use Sylius\PayPalPlugin\Remover\PayPalPaymentDetailsRemoverInterface;

final class RemovePayPalPaymentDetailsListenerTest extends TestCase
{
    public function test_it_removes_paypal_payment_details_from_the_updated_order(): void
    {
        $order = new Order();
        $remover = $this->createMock(PayPalPaymentDetailsRemoverInterface::class);
        $remover->expects(self::once())->method('removeFromOrder')->with($order);

        (new RemovePayPalPaymentDetailsListener($remover))(new ResourceControllerEvent($order));
    }

    public function test_it_throws_an_exception_if_the_subject_is_not_an_order(): void
    {
        $remover = $this->createMock(PayPalPaymentDetailsRemoverInterface::class);
        $remover->expects(self::never())->method('removeFromOrder');

        $this->expectException(\InvalidArgumentException::class);

        (new RemovePayPalPaymentDetailsListener($remover))(new ResourceControllerEvent(new Payment()));
    }
}
