<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Page\Shop\Checkout;

use Sylius\Behat\Page\Shop\Checkout\SelectPaymentPageInterface;

interface PayPalSelectPaymentPageInterface extends SelectPaymentPageInterface
{
    public function hasPaymentMethodSelected(string $name): bool;
}
