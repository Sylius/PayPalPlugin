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

namespace Tests\Sylius\PayPalPlugin\Unit\Checker;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Api\GenericApiInterface;
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Checker\WebhookStateChecker;
use Sylius\PayPalPlugin\Checker\WebhookStateCheckerInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Model\WebhookState;
use Sylius\PayPalPlugin\Provider\HostProvider;
use Sylius\PayPalPlugin\Provider\WebhookIdProviderInterface;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;

final class WebhookStateCheckerTest extends TestCase
{
    private const NOTIFY_URL = 'https://shop.example.com/payment-methods/PAYPAL';

    private const WEBHOOK_URL = 'https://api.sandbox.paypal.com/v1/notifications/webhooks/WH-1';

    private GenericApiInterface&MockObject $genericApi;

    private WebhookIdProviderInterface&MockObject $webhookIdProvider;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private WebhookStateChecker $checker;

    protected function setUp(): void
    {
        $this->genericApi = $this->createMock(GenericApiInterface::class);
        $this->webhookIdProvider = $this->createMock(WebhookIdProviderInterface::class);

        $webhookUrlProvider = $this->createStub(WebhookUrlProviderInterface::class);
        $webhookUrlProvider->method('isNotifyUrl')->willReturnCallback(
            static fn (PaymentMethodInterface $paymentMethod, string $url): bool => in_array($url, [self::NOTIFY_URL, 'http://SHOP.example.com/payment-methods/PAYPAL/'], true),
        );

        $this->configure(['sandbox' => true, 'webhook_id' => 'WH-1']);

        $this->checker = new WebhookStateChecker($this->genericApi, new HostProvider(), $webhookUrlProvider, $this->webhookIdProvider);
    }

    public function test_it_implements_the_webhook_state_checker_interface(): void
    {
        self::assertInstanceOf(WebhookStateCheckerInterface::class, $this->checker);
    }

    public function test_it_finds_a_webhook_on_the_notify_url_with_every_event_up_to_date(): void
    {
        $this->mockWebhook('http://SHOP.example.com/payment-methods/PAYPAL/', array_reverse(WebhookApi::EVENT_TYPES));

        self::assertEquals(WebhookState::upToDate('http://SHOP.example.com/payment-methods/PAYPAL/'), $this->checker->check($this->paymentMethod));
    }

    public function test_it_finds_a_webhook_subscribed_to_every_event_type_up_to_date(): void
    {
        $this->mockWebhook(self::NOTIFY_URL, ['*']);

        self::assertTrue($this->checker->check($this->paymentMethod)->isUpToDate());
    }

    public function test_it_lists_the_event_types_a_webhook_misses(): void
    {
        $this->mockWebhook(self::NOTIFY_URL, array_slice(WebhookApi::EVENT_TYPES, 2));

        self::assertEquals(
            WebhookState::outdated(self::NOTIFY_URL, false, array_slice(WebhookApi::EVENT_TYPES, 0, 2)),
            $this->checker->check($this->paymentMethod),
        );
    }

    public function test_it_finds_a_webhook_still_on_the_legacy_path_outdated(): void
    {
        $this->mockWebhook('https://shop.example.com/paypal-webhook/api/', WebhookApi::EVENT_TYPES);

        $state = $this->checker->check($this->paymentMethod);

        self::assertFalse($state->isUpToDate());
        self::assertTrue($state->pointsElsewhere());
    }

    public function test_it_finds_a_webhook_on_another_host_outdated(): void
    {
        $this->mockWebhook('https://old-shop.example.com/payment-methods/PAYPAL', WebhookApi::EVENT_TYPES);

        self::assertTrue($this->checker->check($this->paymentMethod)->pointsElsewhere());
    }

    public function test_it_does_not_look_the_webhook_up_by_its_url_when_its_id_is_stored(): void
    {
        $this->mockWebhook(self::NOTIFY_URL, WebhookApi::EVENT_TYPES);

        $this->webhookIdProvider->expects(self::never())->method('provide');

        $this->checker->check($this->paymentMethod);
    }

    public function test_it_looks_the_webhook_up_by_its_url_when_no_id_is_stored(): void
    {
        $this->configure(['sandbox' => true]);
        $this->webhookIdProvider->method('provide')->with($this->paymentMethod)->willReturn('WH-1');
        $this->mockWebhook('https://shop.example.com/paypal-webhook/api/', WebhookApi::EVENT_TYPES);

        self::assertEquals(
            WebhookState::outdated('https://shop.example.com/paypal-webhook/api/', true, []),
            $this->checker->check($this->paymentMethod),
        );
    }

    public function test_it_finds_no_webhook_registered_when_none_is_stored_or_found_by_its_url(): void
    {
        $this->configure(['sandbox' => true]);
        $this->webhookIdProvider->method('provide')->willReturn(null);

        $this->genericApi->expects(self::never())->method('get');

        self::assertEquals(WebhookState::notRegistered(), $this->checker->check($this->paymentMethod));
    }

    public function test_it_finds_no_webhook_registered_when_paypal_does_not_know_the_stored_one(): void
    {
        $this->genericApi->method('get')->willThrowException(new PayPalApiNotFoundException('GET', 'v1/notifications/webhooks/WH-1', 404, 'RESOURCE_NOT_FOUND'));

        self::assertEquals(WebhookState::notRegistered(), $this->checker->check($this->paymentMethod));
    }

    public function test_it_asks_the_api_of_the_mode_of_the_payment_method(): void
    {
        $this->configure(['sandbox' => false, 'webhook_id' => 'WH-1']);

        $this->genericApi
            ->expects(self::once())
            ->method('get')
            ->with($this->paymentMethod, 'https://api.paypal.com/v1/notifications/webhooks/WH-1')
            ->willReturn(['url' => self::NOTIFY_URL, 'event_types' => [['name' => '*']]])
        ;

        $this->checker->check($this->paymentMethod);
    }

    public function test_it_lets_any_other_paypal_error_through(): void
    {
        $this->genericApi->method('get')->willThrowException(new PayPalApiRequestException('GET', 'v1/notifications/webhooks/WH-1', 400, 'INVALID_REQUEST'));

        $this->expectException(PayPalApiRequestException::class);

        $this->checker->check($this->paymentMethod);
    }

    /** @param list<string> $eventTypes */
    private function mockWebhook(string $url, array $eventTypes): void
    {
        $this->genericApi
            ->method('get')
            ->with($this->paymentMethod, self::WEBHOOK_URL)
            ->willReturn([
                'id' => 'WH-1',
                'url' => $url,
                'event_types' => array_map(static fn (string $name): array => ['name' => $name], $eventTypes),
            ])
        ;
    }

    /** @param array<string, mixed> $config */
    private function configure(array $config): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setConfig($config);

        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->paymentMethod->method('getCode')->willReturn('PAYPAL');
        $this->paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
    }
}
