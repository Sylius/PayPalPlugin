<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Processor;

use Doctrine\Persistence\ObjectManager;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Processor\PayPalAddressProcessorInterface;

final class PayPalAddressProcessorSpec extends ObjectBehavior
{
    function let(ObjectManager $objectManager): void
    {
        $this->beConstructedWith($objectManager);
    }

    function it_implements_pay_pal_address_processor_interface(): void
    {
        $this->shouldImplement(PayPalAddressProcessorInterface::class);
    }

    function it_updates_order_address(
        OrderInterface $order,
        AddressInterface $orderAddress,
        ObjectManager $objectManager
    ): void {
        $order->getShippingAddress()->willReturn($orderAddress);

        $orderAddress->setCity('New York')->shouldBeCalled();
        $orderAddress->setStreet('Main St. 123')->shouldBeCalled();
        $orderAddress->setPostcode('10001')->shouldBeCalled();
        $orderAddress->setCountryCode('US')->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->process(
            [
                'address_line_1' => 'Main St. 123',
                'admin_area_2' => 'New York',
                'postal_code' => '10001',
                'country_code' => 'US',
            ],
            $order
        );
    }

    function it_updates_order_address_with_two_address_lines(
        OrderInterface $order,
        AddressInterface $orderAddress,
        ObjectManager $objectManager
    ): void {
        $order->getShippingAddress()->willReturn($orderAddress);

        $orderAddress->setCity('New York')->shouldBeCalled();
        $orderAddress->setStreet('Main St. 123')->shouldBeCalled();
        $orderAddress->setPostcode('10001')->shouldBeCalled();
        $orderAddress->setCountryCode('US')->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->process(
            [
                'address_line_1' => 'Main St.',
                'address_line_2' => '123',
                'admin_area_2' => 'New York',
                'postal_code' => '10001',
                'country_code' => 'US',
            ],
            $order
        );
    }

    function it_throws_an_exception_if_address_data_is_missing(
        OrderInterface $order,
        AddressInterface $orderAddress,
        ObjectManager $objectManager
    ): void {
        $order->getShippingAddress()->willReturn($orderAddress);

        $objectManager->flush()->shouldNotBeCalled();
        $this->shouldThrow(\InvalidArgumentException::class)->during('process', [[], $order]);
    }
}
