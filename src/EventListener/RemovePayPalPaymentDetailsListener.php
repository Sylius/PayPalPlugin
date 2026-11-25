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

namespace Sylius\PayPalPlugin\EventListener;

use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Remover\PayPalPaymentDetailsRemoverInterface;
use Webmozart\Assert\Assert;

final readonly class RemovePayPalPaymentDetailsListener
{
    public function __construct(private PayPalPaymentDetailsRemoverInterface $payPalPaymentDetailsRemover)
    {
    }

    public function __invoke(ResourceControllerEvent $event): void
    {
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->payPalPaymentDetailsRemover->removeFromOrder($order);
    }
}
