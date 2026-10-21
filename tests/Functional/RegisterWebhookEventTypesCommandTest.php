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
use Psr\Cache\CacheItemPoolInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RegisterWebhookEventTypesCommandTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    public function test_it_points_the_webhook_registered_on_the_legacy_url_at_the_notify_url_of_the_payment_method(): void
    {
        $this->loadShop();
        $this->mockRegisteredWebhooks();
        $this->payPalHttpClient()->addExpectation('PATCH', 'v1/notifications/webhooks/WH-LEGACY', ['id' => 'WH-LEGACY']);

        self::assertSame(Command::SUCCESS, $this->registerWebhookEventTypes());
    }

    public function test_it_asks_paypal_for_the_webhook_to_move_while_the_webhook_id_refresh_cools_down(): void
    {
        $paymentMethod = $this->loadShop();
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $gatewayConfig->setConfig(['webhook_id' => 'WH-STALE'] + $gatewayConfig->getConfig());
        $this->getEntityManager()->flush();

        /** @var CacheItemPoolInterface $cache */
        $cache = self::getContainer()->get('sylius_paypal.cache');
        $cache->save($cache->getItem('sylius_paypal_webhook_id_refresh_' . hash('sha256', 'PAYPAL'))->set(true));

        $this->mockRegisteredWebhooks();
        $this->payPalHttpClient()->addExpectation('PATCH', 'v1/notifications/webhooks/WH-LEGACY', ['id' => 'WH-LEGACY']);

        self::assertSame(Command::SUCCESS, $this->registerWebhookEventTypes());
    }

    public function test_it_finds_the_webhook_registered_on_the_legacy_url_of_the_channel_hostname_from_the_console(): void
    {
        $paymentMethod = $this->loadShop();
        $paymentMethod->getChannels()->first()->setHostname('paypal-shop.example.com');
        $this->getEntityManager()->flush();

        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks', ['webhooks' => [
            ['id' => 'WH-LEGACY', 'url' => 'https://paypal-shop.example.com/paypal-webhook/api/'],
        ]]);
        $this->payPalHttpClient()->addExpectation('PATCH', 'v1/notifications/webhooks/WH-LEGACY', ['id' => 'WH-LEGACY']);

        self::assertSame(Command::SUCCESS, $this->registerWebhookEventTypes());
    }

    private function loadShop(): PaymentMethodInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_order.yaml']);
        self::getContainer()->get('sylius_paypal.cache')->clear();

        return $fixtures['paypal'];
    }

    private function mockRegisteredWebhooks(): void
    {
        /** @var UrlGeneratorInterface $router */
        $router = self::getContainer()->get('router');

        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks', ['webhooks' => [
            ['id' => 'WH-LEGACY', 'url' => $router->generate('sylius_paypal_webhook_refund_order', [], UrlGeneratorInterface::ABSOLUTE_URL)],
        ]]);
    }

    private function registerWebhookEventTypes(): int
    {
        /** @var Command $command */
        $command = self::getContainer()->get('sylius_paypal.console.command.register_webhook_event_types');

        return (new CommandTester($command))->execute([]);
    }
}
