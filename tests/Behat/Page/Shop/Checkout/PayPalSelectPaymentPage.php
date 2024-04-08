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
