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

namespace Tests\Sylius\PayPalPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Step\Then;
use Behat\Step\When;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Tests\Sylius\PayPalPlugin\Behat\Page\Shop\PayWithPayPalPage;
use Webmozart\Assert\Assert;

final readonly class PayingWithPayPalContext implements Context
{
    public function __construct(
        private SharedStorageInterface $sharedStorage,
        private PayWithPayPalPage $payWithPayPalPage,
    ) {
    }

    #[When('I go to the PayPal payment page of my order')]
    public function iGoToThePayPalPaymentPageOfMyOrder(): void
    {
        /** @var OrderInterface $order */
        $order = $this->sharedStorage->get('order');
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();

        $this->payWithPayPalPage->open([
            '_locale' => 'en_US',
            'orderToken' => $order->getTokenValue(),
            'paymentId' => $payment->getId(),
        ]);
    }

    #[Then('I should be able to pay with PayPal')]
    public function iShouldBeAbleToPayWithPayPal(): void
    {
        Assert::true(
            $this->payWithPayPalPage->hasPayPalButton(),
            'The PayPal wallet button is not rendered on the payment page.',
        );
    }

    #[Then('I should be able to pay by card')]
    public function iShouldBeAbleToPayByCard(): void
    {
        Assert::true(
            $this->payWithPayPalPage->hasCardFields(),
            'The card fields are not rendered on the payment page.',
        );
    }

    #[Then('the payment page should be a part of the shop')]
    public function thePaymentPageShouldBeAPartOfTheShop(): void
    {
        Assert::true(
            $this->payWithPayPalPage->isRenderedInTheShopLayout(),
            'The payment page does not extend the shop layout.',
        );
    }
}
