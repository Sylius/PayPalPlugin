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

namespace Sylius\PayPalPlugin\Twig;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ChangePaymentMethodExtension extends AbstractExtension
{
    /** @param list<string> $allowedPaymentStates */
    public function __construct(private readonly array $allowedPaymentStates)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sylius_paypal_can_change_payment_method', [$this, 'canChangePaymentMethod']),
        ];
    }

    public function canChangePaymentMethod(OrderInterface $order): bool
    {
        if (OrderPaymentStates::STATE_AWAITING_PAYMENT !== $order->getPaymentState()) {
            return false;
        }

        foreach ($order->getPayments() as $payment) {
            if (in_array($payment->getState(), $this->allowedPaymentStates, true)) {
                return true;
            }
        }

        return false;
    }
}
