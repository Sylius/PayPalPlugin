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

namespace Tests\Sylius\PayPalPlugin\Functional;

use ApiTestCase\JsonApiTestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Twig\Environment;

final class CheckoutSummaryPaymentStateTest extends JsonApiTestCase
{
    public function test_it_marks_a_cancelled_payment_on_the_checkout_summary(): void
    {
        $content = $this->renderPaymentRow(PaymentInterface::STATE_CANCELLED);

        self::assertStringContainsString('data-test-payment-state', $content);
        self::assertStringContainsString('Cancelled', $content);
    }

    public function test_it_renders_no_state_for_the_cart_payment_on_the_checkout_summary(): void
    {
        self::assertStringNotContainsString('data-test-payment-state', $this->renderPaymentRow(PaymentInterface::STATE_CART));
    }

    public function test_it_renders_no_state_for_a_processing_payment_on_the_checkout_summary(): void
    {
        self::assertStringNotContainsString('data-test-payment-state', $this->renderPaymentRow(PaymentInterface::STATE_PROCESSING));
    }

    private function renderPaymentRow(string $state): string
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/payment_page_order.yaml']);

        /** @var PaymentInterface $payment */
        $payment = $fixtures['payment_page_paypal_payment'];
        $payment->setState($state);

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig->createTemplate(
            "{% hook 'sylius_shop.checkout.complete.content.form.summary.statuses.payments.list' with { payment } %}",
        )->render(['payment' => $payment]);
    }
}
