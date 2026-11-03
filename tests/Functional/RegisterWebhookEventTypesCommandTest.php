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
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RegisterWebhookEventTypesCommandTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    public function test_it_points_the_webhook_registered_on_the_legacy_url_at_the_notify_url_of_the_payment_method(): void
    {
        $paymentMethod = $this->loadShop();
        $this->mockRegisteredWebhooks($paymentMethod);
        $this->mockWebhookDetails('https://paypal-shop.example.com/paypal-webhook/api/', WebhookApi::EVENT_TYPES);
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

        $this->mockRegisteredWebhooks($paymentMethod);
        $this->mockWebhookDetails('https://paypal-shop.example.com/paypal-webhook/api/', WebhookApi::EVENT_TYPES);
        $this->payPalHttpClient()->addExpectation('PATCH', 'v1/notifications/webhooks/WH-LEGACY', ['id' => 'WH-LEGACY']);

        self::assertSame(Command::SUCCESS, $this->registerWebhookEventTypes());
        self::assertSame('WH-LEGACY', $this->storedWebhookId());
    }

    public function test_it_stores_the_id_of_the_webhook_it_registers(): void
    {
        $paymentMethod = $this->loadShop();
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $gatewayConfig->setConfig(['webhook_id' => 'WH-STALE'] + $gatewayConfig->getConfig());
        $this->getEntityManager()->flush();
        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks', ['webhooks' => []]);
        $this->payPalHttpClient()->addExpectation('POST', 'v1/notifications/webhooks', ['id' => 'WH-NEW']);

        self::assertSame(Command::SUCCESS, $this->registerWebhookEventTypes());
        self::assertSame('WH-NEW', $this->storedWebhookId());
    }

    public function test_it_finds_the_webhook_registered_on_the_legacy_url_of_the_channel_hostname_from_the_console(): void
    {
        $paymentMethod = $this->loadShop();
        $paymentMethod->getChannels()->first()->setHostname('paypal-shop.example.com');
        $this->getEntityManager()->flush();

        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks', ['webhooks' => [
            ['id' => 'WH-LEGACY', 'url' => 'https://paypal-shop.example.com/paypal-webhook/api/'],
        ]]);
        $this->mockWebhookDetails('https://paypal-shop.example.com/paypal-webhook/api/', WebhookApi::EVENT_TYPES);
        $this->payPalHttpClient()->addExpectation('PATCH', 'v1/notifications/webhooks/WH-LEGACY', ['id' => 'WH-LEGACY']);

        self::assertSame(Command::SUCCESS, $this->registerWebhookEventTypes());
    }

    public function test_it_leaves_a_webhook_that_is_already_up_to_date_as_it_is_when_run_again(): void
    {
        $paymentMethod = $this->loadShop();
        /** @var WebhookUrlProviderInterface $webhookUrlProvider */
        $webhookUrlProvider = self::getContainer()->get('sylius_paypal.provider.webhook_url');
        $notifyUrl = $webhookUrlProvider->provide($paymentMethod);

        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks', ['webhooks' => [
            ['id' => 'WH-LEGACY', 'url' => $notifyUrl],
        ]]);
        $this->mockWebhookDetails($notifyUrl, WebhookApi::EVENT_TYPES);

        $commandTester = $this->commandTester();

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringContainsString('Webhook WH-LEGACY of "PAYPAL" is already up to date', $commandTester->getDisplay());
        self::assertSame('WH-LEGACY', $this->storedWebhookId());
    }

    /** @param list<string> $eventTypes */
    private function mockWebhookDetails(string $url, array $eventTypes): void
    {
        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks/WH-LEGACY', [
            'id' => 'WH-LEGACY',
            'url' => $url,
            'event_types' => array_map(static fn (string $name): array => ['name' => $name], $eventTypes),
        ]);
    }

    private function storedWebhookId(): mixed
    {
        self::getContainer()->get('sylius.manager.payment_method')->clear();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = self::getContainer()->get('sylius.repository.payment_method')->findOneBy(['code' => 'PAYPAL']);

        return $paymentMethod->getGatewayConfig()?->getConfig()['webhook_id'] ?? null;
    }

    private function loadShop(): PaymentMethodInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_order.yaml']);
        self::getContainer()->get('sylius_paypal.cache')->clear();

        return $fixtures['paypal'];
    }

    private function mockRegisteredWebhooks(PaymentMethodInterface $paymentMethod): void
    {
        /** @var WebhookUrlProviderInterface $webhookUrlProvider */
        $webhookUrlProvider = self::getContainer()->get('sylius_paypal.provider.webhook_url');

        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks', ['webhooks' => [
            ['id' => 'WH-LEGACY', 'url' => $webhookUrlProvider->provideCandidates($paymentMethod)[1]],
        ]]);
    }

    private function registerWebhookEventTypes(): int
    {
        return $this->commandTester()->execute([]);
    }

    private function commandTester(): CommandTester
    {
        /** @var Command $command */
        $command = self::getContainer()->get('sylius_paypal.console.command.register_webhook_event_types');

        return new CommandTester($command);
    }
}
