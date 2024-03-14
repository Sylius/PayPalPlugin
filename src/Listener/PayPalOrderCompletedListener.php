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

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Processor\PayPalOrderCompleteProcessor;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

final class PayPalOrderCompletedListener
{

    public function __construct(private readonly PayPalOrderCompleteProcessor $completeProcessor)
    { }

    /** @phpstan-ignore-next-line */
    public function __invoke(CompletedEvent $event)
    {
        /** @var OrderInterface $order */
        /** @phpstan-ignore-next-line */
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->completeProcessor->completePayPalOrder($order);
    }
}
