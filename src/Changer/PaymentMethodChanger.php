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

namespace Sylius\PayPalPlugin\Changer;

use Sylius\Bundle\ApiBundle\Changer\PaymentMethodChangerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Remover\PayPalPaymentDetailsRemoverInterface;

final readonly class PaymentMethodChanger implements PaymentMethodChangerInterface
{
    public function __construct(
        private PaymentMethodChangerInterface $decoratedPaymentMethodChanger,
        private PayPalPaymentDetailsRemoverInterface $payPalPaymentDetailsRemover,
    ) {
    }

    public function changePaymentMethod(string $paymentMethodCode, mixed $paymentId, OrderInterface $order): OrderInterface
    {
        $order = $this->decoratedPaymentMethodChanger->changePaymentMethod($paymentMethodCode, $paymentId, $order);

        $this->payPalPaymentDetailsRemover->removeFromOrder($order);

        return $order;
    }
}
