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

use Behat\Mink\Element\NodeElement;
use FriendsOfBehat\PageObjectExtension\Element\Element;
use Webmozart\Assert\Assert;

final class PaymentMethodListElement extends Element implements PaymentMethodListElementInterface
{
    public function hasPaymentSource(string $paymentMethodName, string $paymentSource): bool
    {
        return $this->getRow($paymentMethodName)->has('css', sprintf('[data-test-paypal-payment-source="%s"]', $paymentSource));
    }

    public function getMode(string $paymentMethodName): ?string
    {
        return $this->getRow($paymentMethodName)->find('css', '[data-test-paypal-mode]')?->getAttribute('data-test-paypal-mode');
    }

    public function getMorePaymentSources(string $paymentMethodName): ?string
    {
        return $this->getRow($paymentMethodName)->find('css', '[data-test-paypal-more-payment-sources]')?->getAttribute('data-bs-title');
    }

    public function hasSuggestedPayPalPaymentMethod(): bool
    {
        return $this->getDocument()->has('css', '.payment-method-list__item--suggested.payment-method-list__item--paypal');
    }

    private function getRow(string $paymentMethodName): NodeElement
    {
        $row = $this->getDocument()->find('css', sprintf('tbody tr:contains("%s")', $paymentMethodName));
        Assert::notNull($row, sprintf('There is no "%s" payment method in the list.', $paymentMethodName));

        return $row;
    }
}
