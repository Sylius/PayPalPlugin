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

namespace Tests\Sylius\PayPalPlugin\Unit\EventListener\Cart;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\TokenAssigner\OrderTokenAssignerInterface;
use Sylius\PayPalPlugin\EventListener\Cart\AssignCartTokenListener;
use Symfony\Component\EventDispatcher\GenericEvent;

final class AssignCartTokenListenerTest extends TestCase
{
    private OrderTokenAssignerInterface&MockObject $orderTokenAssigner;

    private ObjectManager&MockObject $orderManager;

    private AssignCartTokenListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderTokenAssigner = $this->createMock(OrderTokenAssignerInterface::class);
        $this->orderManager = $this->createMock(ObjectManager::class);

        $this->listener = new AssignCartTokenListener($this->orderTokenAssigner, $this->orderManager);
    }

    /** @test */
    public function it_assigns_a_token_and_flushes_when_an_item_is_added_to_the_cart(): void
    {
        $cart = $this->createMock(OrderInterface::class);
        $command = $this->createMock(AddToCartCommandInterface::class);
        $command->method('getCart')->willReturn($cart);
        $event = new GenericEvent($command);

        $this->orderTokenAssigner->expects(self::once())->method('assignTokenValueIfNotSet')->with($cart);
        $this->orderManager->expects(self::once())->method('flush');

        ($this->listener)($event);
    }

    /** @test */
    public function it_rejects_a_subject_that_is_not_an_add_to_cart_command(): void
    {
        $event = new GenericEvent(new \stdClass());

        $this->expectException(\InvalidArgumentException::class);

        ($this->listener)($event);
    }
}
