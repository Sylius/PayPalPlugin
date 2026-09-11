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

namespace Sylius\PayPalPlugin\EventListener\Cart;

use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\TokenAssigner\OrderTokenAssignerInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Webmozart\Assert\Assert;

// Core's own (non-PayPal) add-to-cart LiveComponent never assigns an order token either - it only
// happens on checkout completion - so a cart built entirely through it would otherwise still have
// none by the time the v6 cart-page placement is rendered.
final class AssignCartTokenListener
{
    public function __construct(
        private readonly OrderTokenAssignerInterface $orderTokenAssigner,
        private readonly ObjectManager $orderManager,
    ) {
    }

    public function __invoke(GenericEvent $event): void
    {
        $command = $event->getSubject();
        Assert::isInstanceOf($command, AddToCartCommandInterface::class);

        $cart = $command->getCart();
        Assert::isInstanceOf($cart, OrderInterface::class);

        $this->orderTokenAssigner->assignTokenValueIfNotSet($cart);
        $this->orderManager->flush();
    }
}
