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
    }

    public function test_it_shows_the_webhook_state_of_a_paypal_payment_method_on_its_edit_page_without_asking_paypal(): void
    {
        self::assertStringContainsString('data-test-paypal-webhook-state="unknown"', $this->editPage());
    }

    public function test_it_flags_a_stored_webhook_that_misses_an_event_type_the_plugin_handles(): void
    {
        $this->storeWebhook($this->notifyUrl(), array_slice(WebhookApi::EVENT_TYPES, 1));

        $rendered = (string) $this->webhookState('alert')->render();

        self::assertStringContainsString('data-test-paypal-webhook-state="out-of-date"', $rendered);
        self::assertStringContainsString(WebhookApi::EVENT_TYPES[0], $rendered);
    }

    public function test_it_flags_a_payment_method_whose_webhook_was_stored_without_its_url_and_event_types(): void
    {
        self::assertStringContainsString('data-test-paypal-webhook-state="unknown"', (string) $this->webhookState('badge')->render());
    }

    public function test_it_flags_a_stored_webhook_on_the_legacy_path(): void
    {
        $legacyUrl = 'https://localhost/paypal-webhook/api/';
        $this->storeWebhook($legacyUrl, WebhookApi::EVENT_TYPES);

        $rendered = (string) $this->webhookState('alert')->render();

        self::assertStringContainsString('data-test-paypal-webhook-state="out-of-date"', $rendered);
        self::assertStringContainsString($legacyUrl, $rendered);
    }

    public function test_it_shows_nothing_for_a_stored_webhook_that_is_up_to_date(): void
    {
        $this->storeWebhook($this->notifyUrl(), WebhookApi::EVENT_TYPES);

        self::assertStringNotContainsString('paypal-webhook-state', (string) $this->webhookState('badge')->render());
        self::assertStringNotContainsString('paypal-webhook-state', (string) $this->webhookState('alert')->render());
    }

    public function test_it_shows_the_stored_webhook_url_on_the_edit_page(): void
    {
        $this->storeWebhook('https://shop.example.com/paypal/webhook', WebhookApi::EVENT_TYPES);

        self::assertStringContainsString('data-test-paypal-webhook-url="https://shop.example.com/paypal/webhook"', $this->editPage());
    }

    public function test_it_shows_an_unknown_webhook_url_on_the_edit_page_when_none_is_stored(): void
    {
        self::assertStringContainsString('data-test-paypal-webhook-url="unknown"', $this->editPage());
    }

    public function test_it_updates_an_outdated_webhook_from_the_edit_page_and_shows_it_up_to_date_afterwards(): void
    {
        $this->storeWebhook($this->notifyUrl(), array_slice(WebhookApi::EVENT_TYPES, 1));
        $missingOne = $this->webhook('WH-1', array_slice(WebhookApi::EVENT_TYPES, 1));

        $component = $this->webhookState('alert');
        self::assertStringContainsString('data-test-paypal-webhook-update-button', (string) $component->render());

        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks', ['webhooks' => [$missingOne]]);
        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks/WH-1', $missingOne);
        $this->payPalHttpClient()->addExpectation('PATCH', 'v1/notifications/webhooks/WH-1', ['id' => 'WH-1']);

        $rendered = (string) $component->call('updateWebhook')->render();

        self::assertStringContainsString('data-test-paypal-webhook-update="success"', $rendered);
        self::assertStringNotContainsString('data-test-paypal-webhook-state', $rendered);
        self::assertSame(WebhookApi::EVENT_TYPES, array_column($this->sentPatch()[1]['value'], 'name'));
    }

    public function test_it_keeps_the_webhook_on_the_channel_hostname_when_updating_it_from_another_host(): void
    {
        $this->paymentMethod->getChannels()->first()->setHostname('paypal-shop.example.com');
        $channelUrl = 'https://paypal-shop.example.com/payment-methods/PAYPAL';
        $this->storeWebhook($channelUrl, array_slice(WebhookApi::EVENT_TYPES, 1));
        $missingOne = ['url' => $channelUrl] + $this->webhook('WH-1', array_slice(WebhookApi::EVENT_TYPES, 1));
        $component = $this->webhookState('alert');
        $component->render();

        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks', ['webhooks' => [$missingOne]]);
        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks/WH-1', $missingOne);
        $this->payPalHttpClient()->addExpectation('PATCH', 'v1/notifications/webhooks/WH-1', ['id' => 'WH-1']);

        $rendered = (string) $component->call('updateWebhook')->render();

        self::assertStringContainsString('data-test-paypal-webhook-update="success"', $rendered);
        self::assertSame($channelUrl, $this->sentPatch()[0]['value']);
    }

    public function test_it_tells_why_paypal_refused_to_update_the_webhook(): void
    {
        $this->storeWebhook($this->notifyUrl(), array_slice(WebhookApi::EVENT_TYPES, 1));
        $missingOne = $this->webhook('WH-1', array_slice(WebhookApi::EVENT_TYPES, 1));
        $component = $this->webhookState('alert');
        $component->render();

        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks', ['webhooks' => [$missingOne]]);
        $this->payPalHttpClient()->addExpectation('GET', 'v1/notifications/webhooks/WH-1', $missingOne);
        $this->payPalHttpClient()->addExpectation('PATCH', 'v1/notifications/webhooks/WH-1', [
            'name' => 'VALIDATION_ERROR',
            'message' => 'Invalid data provided',
            'debug_id' => 'DEBUG_ID',
        ], 400);

        $rendered = (string) $component->call('updateWebhook')->render();

        self::assertStringContainsString('data-test-paypal-webhook-update="error"', $rendered);
        self::assertStringContainsString('VALIDATION_ERROR', $rendered);
        self::assertStringContainsString('data-test-paypal-webhook-state="out-of-date"', $rendered);
    }

    /**
     * @param list<string> $eventTypes
     *
     * @return array<string, mixed>
     */
    private function webhook(string $id, array $eventTypes): array
    {
        return [
            'id' => $id,
            'url' => $this->notifyUrl(),
            'event_types' => array_map(static fn (string $name): array => ['name' => $name], $eventTypes),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function sentPatch(): array
    {
        foreach ($this->payPalHttpClient()->getSentRequests() as $sentRequest) {
            if ('PATCH' === $sentRequest['method']) {
                return $sentRequest['body'];
            }
        }

        self::fail('No PATCH was sent to PayPal.');
    }

    private function editPage(): string
    {
        $this->client->loginUser($this->admin, 'admin');
        $this->client->request('GET', sprintf('/admin/payment-methods/%d/edit', (int) $this->paymentMethod->getId()));

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        return (string) $this->client->getResponse()->getContent();
    }

    private function webhookState(string $variant): TestLiveComponent
    {
        $this->client->getContainer()->get('router')->getContext()->setParameter('_locale', 'en_US');

        return $this->createLiveComponent(
            'sylius_paypal:webhook_state',
            ['paymentMethodId' => $this->paymentMethod->getId(), 'variant' => $variant],
            $this->client,
        )->actingAs($this->admin, 'admin');
    }

    /** @param list<string> $eventTypes */
    private function storeWebhook(string $webhookUrl, array $eventTypes): void
    {
        $gatewayConfig = $this->paymentMethod->getGatewayConfig();
        self::assertNotNull($gatewayConfig);
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), [
            'webhook_id' => 'WH-1',
            'webhook_url' => $webhookUrl,
            'webhook_event_types' => $eventTypes,
        ]));
        $this->getEntityManager()->flush();
    }

    private function notifyUrl(): string
    {
        /** @var WebhookUrlProviderInterface $webhookUrlProvider */
        $webhookUrlProvider = self::getContainer()->get('sylius_paypal.provider.webhook_url');

        return $webhookUrlProvider->provide($this->paymentMethod);
    }
}
