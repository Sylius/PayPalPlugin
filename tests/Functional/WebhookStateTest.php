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
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class WebhookStateTest extends JsonApiTestCase
{
    use InteractsWithLiveComponents;
    use MocksPayPalApiTrait;

    private PaymentMethodInterface $paymentMethod;

    private AdminUserInterface $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = $this->loadFixturesFromFiles([
            'resources/shop.yaml',
            'resources/shipping.yaml',
            'resources/authorized_paypal_order.yaml',
        ]);
        $this->paymentMethod = $fixtures['paypal'];
        $this->admin = $fixtures['admin'];
        self::getContainer()->get('sylius_paypal.cache')->clear();
    }

    public function test_it_loads_the_webhook_state_of_a_paypal_payment_method_lazily_on_its_edit_page(): void
    {
        $this->client->loginUser($this->admin, 'admin');
        $this->client->request('GET', sprintf('/admin/payment-methods/%d/edit', (int) $this->paymentMethod->getId()));
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('sylius_paypal:webhook_state', (string) $this->client->getResponse()->getContent());
    }

    public function test_it_flags_a_webhook_that_misses_an_event_type_the_plugin_handles(): void
    {
        $this->storeWebhookId('WH-1');
        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks/WH-1', [
            'id' => 'WH-1',
            'url' => $this->notifyUrl(),
            'event_types' => array_map(static fn (string $name): array => ['name' => $name], array_slice(WebhookApi::EVENT_TYPES, 1)),
        ]);

        $rendered = (string) $this->webhookState('alert')->render();

        self::assertStringContainsString('data-test-paypal-webhook-state="out-of-date"', $rendered);
        self::assertStringContainsString(WebhookApi::EVENT_TYPES[0], $rendered);
    }

    public function test_it_flags_a_payment_method_without_a_webhook(): void
    {
        self::assertStringContainsString('data-test-paypal-webhook-state="not-registered"', (string) $this->webhookState('badge')->render());
    }

    public function test_it_shows_nothing_for_a_webhook_that_is_up_to_date_and_asks_paypal_only_once(): void
    {
        $this->storeWebhookId('WH-1');
        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks/WH-1', [
            'id' => 'WH-1',
            'url' => $this->notifyUrl(),
            'event_types' => array_map(static fn (string $name): array => ['name' => $name], WebhookApi::EVENT_TYPES),
        ]);

        self::assertStringNotContainsString('paypal-webhook-state', (string) $this->webhookState('badge')->render());
        self::assertStringNotContainsString('paypal-webhook-state', (string) $this->webhookState('alert')->render());
    }

    private function webhookState(string $variant): TestLiveComponent
    {
        return $this->createLiveComponent(
            'sylius_paypal:webhook_state',
            ['paymentMethodId' => $this->paymentMethod->getId(), 'variant' => $variant],
            $this->client,
        )->actingAs($this->admin, 'admin');
    }

    private function storeWebhookId(string $webhookId): void
    {
        $gatewayConfig = $this->paymentMethod->getGatewayConfig();
        self::assertNotNull($gatewayConfig);
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), ['sandbox' => true, 'webhook_id' => $webhookId]));
        $this->getEntityManager()->flush();
    }

    private function notifyUrl(): string
    {
        /** @var WebhookUrlProviderInterface $webhookUrlProvider */
        $webhookUrlProvider = self::getContainer()->get('sylius_paypal.provider.webhook_url');

        return $webhookUrlProvider->provide($this->paymentMethod);
    }
}
