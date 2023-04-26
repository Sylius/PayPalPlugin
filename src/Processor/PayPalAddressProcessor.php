<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Webmozart\Assert\Assert;

final class PayPalAddressProcessor implements PayPalAddressProcessorInterface
{
    private ObjectManager $objectManager;

    public function __construct(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @param array<string, string> $address
     */
    public function process(array $address, OrderInterface $order): void
    {
        $orderAddress = $order->getShippingAddress();

        if (null === $orderAddress) {
            return;
        }

        Assert::keyExists($address, 'admin_area_2');
        Assert::keyExists($address, 'address_line_1');
        Assert::keyExists($address, 'postal_code');
        Assert::keyExists($address, 'country_code');

        $street = $address['address_line_1'] . (isset($address['address_line_2']) ? ' ' . $address['address_line_2'] : '');

        $orderAddress->setCity($address['admin_area_2']);
        $orderAddress->setStreet($street);
        $orderAddress->setPostcode($address['postal_code']);
        $orderAddress->setCountryCode($address['country_code']);

        $this->objectManager->flush();
    }
}
