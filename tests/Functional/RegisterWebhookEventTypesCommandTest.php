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
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RegisterWebhookEventTypesCommandTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    public function test_it_reports_the_https_url_paypal_refused_to_register_the_webhook_at(): void
    {
        $paymentMethod = $this->loadShop();
        $paymentMethod->getChannels()->first()->setHostname(null);
        $this->getEntityManager()->flush();

        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks', ['webhooks' => []]);
        $this->payPalHttpClient()->addExpectation('POST', 'v1/notifications/webhooks', [
            'name' => 'VALIDATION_ERROR',
            'message' => 'Invalid data provided',
            'debug_id' => 'DEBUG_ID',
        ], 400);

        /** @var Command $command */
        $command = self::getContainer()->get('sylius_paypal.console.command.register_webhook_event_types');
        $commandTester = new CommandTester($command);

        self::assertSame(Command::FAILURE, $commandTester->execute([]));
        self::assertStringContainsString('"https://localhost/payment-methods/PAYPAL"', preg_replace('/\s+/', ' ', $commandTester->getDisplay()) ?? '');
    }

    public function test_it_points_the_webhook_registered_on_the_legacy_url_at_the_notify_url_of_the_payment_method(): void
    {
        $paymentMethod = $this->loadShop();
        $this->mockRegisteredWebhooks($paymentMethod);
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

        $webhook = ['id' => 'WH-LEGACY', 'url' => 'https://paypal-shop.example.com/paypal-webhook/api/'];
        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks', ['webhooks' => [$webhook]]);
        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks/WH-LEGACY', $webhook);
        $this->payPalHttpClient()->addExpectation('PATCH', 'v1/notifications/webhooks/WH-LEGACY', ['id' => 'WH-LEGACY']);

        self::assertSame(Command::SUCCESS, $this->registerWebhookEventTypes());
    }

    public function test_it_reports_a_webhook_paypal_found_nothing_to_change_in_as_already_up_to_date(): void
    {
        $paymentMethod = $this->loadShop();
        /** @var WebhookUrlProviderInterface $webhookUrlProvider */
        $webhookUrlProvider = self::getContainer()->get('sylius_paypal.provider.webhook_url');

        $webhook = ['id' => 'WH-LEGACY', 'url' => $webhookUrlProvider->provide($paymentMethod)];
        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks', ['webhooks' => [$webhook]]);
        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks/WH-LEGACY', $webhook);
        $this->payPalHttpClient()->addExpectation('PATCH', 'v1/notifications/webhooks/WH-LEGACY', [
            'name' => 'WEBHOOK_PATCH_REQUEST_NO_CHANGE',
            'message' => 'No change in webhook',
            'debug_id' => 'DEBUG_ID',
        ], 400);

        $commandTester = $this->commandTester();

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringContainsString('Webhook WH-LEGACY of "PAYPAL" is already up to date', $commandTester->getDisplay());
        self::assertSame('WH-LEGACY', $this->storedWebhookId());
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

        $webhook = ['id' => 'WH-LEGACY', 'url' => $webhookUrlProvider->provideCandidates($paymentMethod)[1]];
        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks', ['webhooks' => [$webhook]]);
        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks/WH-LEGACY', $webhook);
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
