<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Twig;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class OrderAddressExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('sylius_is_billing_address_missing', [$this, 'isBillingAddressMissing']),
        ];
    }

    public function isBillingAddressMissing(OrderInterface $order): bool
    {
        /** @var AddressInterface $billingAddress */
        $billingAddress = $order->getBillingAddress();

        return
            !$order->isShippingRequired() &&
            $billingAddress->getStreet() === '' &&
            $billingAddress->getPostcode() === '' &&
            $billingAddress->getCity() === ''
        ;
    }
}
