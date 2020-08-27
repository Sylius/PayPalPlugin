<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Processor;

use Doctrine\Persistence\ObjectManager;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class PayPalAddressProcessorSpec extends ObjectBehavior
{
    function let(ObjectManager $objectManager): void
    {
        $this->beConstructedWith($objectManager);
    }

    function it_throws_error_if_address_data_is_missing(
        OrderInterface $order,
        AddressInterface $orderAddress,
        ObjectManager $objectManager
    ): void {
        $order->getShippingAddress()->willReturn($orderAddress);

        $objectManager->flush()->shouldNotBeCalled();
        $this->shouldThrow(\InvalidArgumentException::class)->during('process', [[], $order]);
    }
}
