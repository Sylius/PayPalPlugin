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

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Checker\WebhookStateChecker;
use Sylius\PayPalPlugin\Checker\WebhookStateCheckerInterface;
use Sylius\PayPalPlugin\Model\WebhookState;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;

final class WebhookStateCheckerTest extends TestCase
{
    private const NOTIFY_URL = 'https://shop.example.com/payment-methods/PAYPAL';

    private WebhookStateChecker $checker;

    protected function setUp(): void
    {
        $webhookUrlProvider = $this->createStub(WebhookUrlProviderInterface::class);
        $webhookUrlProvider->method('isNotifyUrl')->willReturnCallback(
            static fn (PaymentMethodInterface $paymentMethod, string $url): bool => in_array($url, [self::NOTIFY_URL, 'http://SHOP.example.com/payment-methods/PAYPAL/'], true),
        );

        $this->checker = new WebhookStateChecker($webhookUrlProvider);
    }

    public function test_it_implements_the_webhook_state_checker_interface(): void
    {
        self::assertInstanceOf(WebhookStateCheckerInterface::class, $this->checker);
    }

    public function test_it_finds_a_webhook_stored_on_the_notify_url_with_every_event_type_up_to_date(): void
    {
        $state = $this->checker->check($this->paymentMethod('http://SHOP.example.com/payment-methods/PAYPAL/', array_reverse(WebhookApi::EVENT_TYPES)));

        self::assertEquals(WebhookState::upToDate('http://SHOP.example.com/payment-methods/PAYPAL/'), $state);
    }

    public function test_it_lists_the_event_types_the_stored_webhook_misses(): void
    {
        $state = $this->checker->check($this->paymentMethod(self::NOTIFY_URL, array_slice(WebhookApi::EVENT_TYPES, 2)));

        self::assertEquals(WebhookState::outdated(self::NOTIFY_URL, false, array_slice(WebhookApi::EVENT_TYPES, 0, 2)), $state);
    }

    public function test_it_finds_a_webhook_stored_on_the_legacy_path_outdated(): void
    {
        $state = $this->checker->check($this->paymentMethod('https://shop.example.com/paypal-webhook/api/', WebhookApi::EVENT_TYPES));

        self::assertFalse($state->isUpToDate());
        self::assertTrue($state->pointsElsewhere());
    }

    public function test_it_finds_a_webhook_stored_on_another_host_outdated(): void
    {
        $state = $this->checker->check($this->paymentMethod('https://old-shop.example.com/payment-methods/PAYPAL', WebhookApi::EVENT_TYPES));

        self::assertTrue($state->pointsElsewhere());
    }

    public function test_it_does_not_know_the_state_of_a_webhook_stored_without_its_url_and_event_types(): void
    {
        self::assertEquals(WebhookState::unknown(), $this->checker->check($this->paymentMethod(null, null)));
    }

    public function test_it_does_not_know_the_state_of_a_webhook_stored_without_its_event_types(): void
    {
        self::assertEquals(WebhookState::unknown(), $this->checker->check($this->paymentMethod(self::NOTIFY_URL, null)));
    }

    /** @param list<string>|null $eventTypes */
    private function paymentMethod(?string $webhookUrl, ?array $eventTypes): PaymentMethod
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setConfig(array_filter([
            'webhook_id' => 'WH-1',
            'webhook_url' => $webhookUrl,
            'webhook_event_types' => $eventTypes,
        ], static fn (mixed $value): bool => null !== $value));

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCode('PAYPAL');
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
