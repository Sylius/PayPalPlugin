<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Page\Shop\Checkout;

use Sylius\Behat\Page\Shop\Checkout\SelectPaymentPage;

class PayPalSelectPaymentPage extends SelectPaymentPage implements PayPalSelectPaymentPageInterface
{
    public function hasPaymentMethodSelected(string $paymentMethodName): bool
    {
        $paymentMethodOptionElement = $this->getElement('payment_method_option', ['%payment_method%' => $paymentMethodName]);

        return $paymentMethodOptionElement->getAttribute('checked') === 'checked';
    }
}
