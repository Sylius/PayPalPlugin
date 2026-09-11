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

namespace Sylius\PayPalPlugin\EventListener\Workflow;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\TokenAssigner\OrderTokenAssignerInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

// Core only assigns an order token once checkout fully completes - the address/select-shipping/
// skip-shipping transitions this listens on are the earliest point every order genuinely passes
// through before checkout's payment step can ever be reached, so a token is guaranteed to exist by
// the time the v6 payment-page placement is rendered.
final class AssignOrderTokenOnCheckoutListener
{
    public function __construct(
        private readonly OrderTokenAssignerInterface $orderTokenAssigner,
        private readonly ObjectManager $orderManager,
    ) {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->orderTokenAssigner->assignTokenValueIfNotSet($order);
        $this->orderManager->flush();
    }
}
