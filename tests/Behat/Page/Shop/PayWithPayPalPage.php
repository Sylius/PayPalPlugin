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

namespace Tests\Sylius\PayPalPlugin\Behat\Page\Shop;

use Sylius\Behat\Page\SyliusPage;

final class PayWithPayPalPage extends SyliusPage
{
    public function getRouteName(): string
    {
        return 'sylius_paypal_shop_pay_with_paypal_form';
    }

    public function hasPayPalButton(): bool
    {
        return $this->hasElement('paypal_button');
    }

    public function hasTrustlyButton(): bool
    {
        return $this->hasElement('trustly_button');
    }

    public function hasCardFields(): bool
    {
        return $this->hasElement('card_fields');
    }

    public function isRenderedInTheShopLayout(): bool
    {
        return $this->hasElement('shop_layout_body');
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'card_fields' => '[data-controller~="sylius--paypal-plugin--paypal-payment-card-fields"]',
            'paypal_button' => '[data-controller~="sylius--paypal-plugin--paypal-payment-wallet-button"]',
            'shop_layout_body' => 'body[data-route="sylius_paypal_shop_pay_with_paypal_form"]',
            'trustly_button' => '[data-test-paypal-redirect-button="trustly"]',
        ]);
    }
}
