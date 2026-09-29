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

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Webmozart\Assert\Assert;

final readonly class ReplacementOrderPaymentProcessor implements OrderProcessorInterface
{
    public function __construct(private OrderProcessorInterface $orderPaymentProcessor)
    {
    }

    public function process(OrderInterface $order): void
    {
        Assert::isInstanceOf($order, CoreOrderInterface::class);

        if (null !== $order->getLastPayment(PaymentInterface::STATE_NEW) || null !== $order->getLastPayment(PaymentInterface::STATE_CART)) {
            return;
        }

        $this->orderPaymentProcessor->process($order);
    }
}
