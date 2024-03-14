<?php

declare(strict_types=1);

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\PayPalPlugin\Listener;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Processor\PaymentRefundProcessorInterface;
use Symfony\Component\Workflow\Event\EnterEvent;
use Webmozart\Assert\Assert;

final class PayPalPaymentRefundedListener
{
    public function __construct(private readonly PaymentRefundProcessorInterface $paymentRefundProcessor)
    {}

    /** @phpstan-ignore-next-line */
    public function __invoke(EnterEvent $event)
    {
        /** @var PaymentInterface $payment */
        /** @phpstan-ignore-next-line */
        $payment = $event->getSubject();
        Assert::isInstanceOf($payment, PaymentInterface::class);

        $this->paymentRefundProcessor->refund($payment);
    }
}