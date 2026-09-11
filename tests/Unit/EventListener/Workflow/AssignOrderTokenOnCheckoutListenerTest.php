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

namespace Tests\Sylius\PayPalPlugin\Unit\EventListener\Workflow;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\TokenAssigner\OrderTokenAssignerInterface;
use Sylius\PayPalPlugin\EventListener\Workflow\AssignOrderTokenOnCheckoutListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;

final class AssignOrderTokenOnCheckoutListenerTest extends TestCase
{
    private OrderTokenAssignerInterface&MockObject $orderTokenAssigner;

    private ObjectManager&MockObject $orderManager;

    private AssignOrderTokenOnCheckoutListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderTokenAssigner = $this->createMock(OrderTokenAssignerInterface::class);
        $this->orderManager = $this->createMock(ObjectManager::class);

        $this->listener = new AssignOrderTokenOnCheckoutListener($this->orderTokenAssigner, $this->orderManager);
    }

    /** @test */
    public function it_assigns_a_token_and_flushes_when_the_checkout_transition_completes(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $event = new CompletedEvent($order, new Marking());

        $this->orderTokenAssigner->expects(self::once())->method('assignTokenValueIfNotSet')->with($order);
        $this->orderManager->expects(self::once())->method('flush');

        ($this->listener)($event);
    }

    /** @test */
    public function it_rejects_a_subject_that_is_not_an_order(): void
    {
        $event = new CompletedEvent(new \stdClass(), new Marking());

        $this->expectException(\InvalidArgumentException::class);

        ($this->listener)($event);
    }
}
