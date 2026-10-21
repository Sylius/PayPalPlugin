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

namespace Tests\Sylius\PayPalPlugin\Behat\Element;

use FriendsOfBehat\PageObjectExtension\Element\Element;

final class ConnectPayPalAccountElement extends Element implements ConnectPayPalAccountElementInterface
{
    public function canConnectAnotherPayPalAccount(): bool
    {
        return $this->hasElement('add_paypal_account');
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'add_paypal_account' => '.btn-group button.dropdown-toggle:contains("Add PayPal account")',
        ]);
    }
}
