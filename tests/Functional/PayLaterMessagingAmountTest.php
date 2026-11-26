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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\Request;

final class PayLaterMessagingAmountTest extends JsonApiTestCase
{
    private const CART_TEMPLATE = '@SyliusPayPalPlugin/shop/cart/index/content/form/sections/general/paypal_messaging.html.twig';

    public function test_it_gives_the_pay_later_message_the_cart_total_with_cents(): void
    {
        self::assertStringContainsString('amount-value="39.51"', $this->renderCartMessage('USD'));
    }

    public function test_it_gives_the_pay_later_message_the_cart_total_without_decimals_in_a_currency_without_them(): void
    {
        self::assertStringContainsString('amount-value="40"', $this->renderCartMessage('JPY'));
    }

    private function renderCartMessage(string $currencyCode): string
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = self::getContainer()->get('sylius.repository.payment_method')->findOneBy(['code' => 'PAYPAL']);
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), ['pay_later_enabled' => true, 'messaging_enabled' => true]));
        $this->getEntityManager()->flush();

        $cart = $this->createStub(OrderInterface::class);
        $cart->method('getTotal')->willReturn(3951);
        $cart->method('getCurrencyCode')->willReturn($currencyCode);

        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push(Request::create('http://localhost/en_US/cart/'));

        try {
            return self::getContainer()->get('twig')->render(self::CART_TEMPLATE, [
                'hookable_metadata' => ['context' => ['resource' => $cart]],
            ]);
        } finally {
            $requestStack->pop();
        }
    }
}
