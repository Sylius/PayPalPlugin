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

namespace Tests\Sylius\PayPalPlugin\Unit\Registrar;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\UpdateWebhookApiInterface;
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Api\WebhookApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookNotRegisteredException;
use Sylius\PayPalPlugin\Model\WebhookRegistration;
use Sylius\PayPalPlugin\Provider\WebhookIdProviderInterface;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;
use Sylius\PayPalPlugin\Registrar\SellerWebhookEventTypesRegistrar;
use Sylius\PayPalPlugin\Registrar\SellerWebhookEventTypesRegistrarInterface;
use Sylius\PayPalPlugin\Updater\WebhookIdUpdaterInterface;

final class SellerWebhookEventTypesRegistrarTest extends TestCase
{
    private WebhookIdProviderInterface&MockObject $webhookIdProvider;

    private UpdateWebhookApiInterface&MockObject $updateWebhookApi;

    private WebhookApiInterface&MockObject $webhookApi;

    private WebhookIdUpdaterInterface&MockObject $webhookIdUpdater;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private SellerWebhookEventTypesRegistrar $registrar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webhookIdProvider = $this->createMock(WebhookIdProviderInterface::class);
        $this->updateWebhookApi = $this->createMock(UpdateWebhookApiInterface::class);
        $this->webhookApi = $this->createMock(WebhookApiInterface::class);
        $this->webhookIdUpdater = $this->createMock(WebhookIdUpdaterInterface::class);

        $webhookUrlProvider = $this->createStub(WebhookUrlProviderInterface::class);
        $webhookUrlProvider->method('provide')->willReturnCallback(
            fn (PaymentMethodInterface $paymentMethod): string => 'https://shop.example.com/payment-methods/' . $paymentMethod->getCode(),
        );

        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->paymentMethod->method('getCode')->willReturn('PAYPAL');

        $this->registrar = new SellerWebhookEventTypesRegistrar(
            $this->webhookIdProvider,
            $this->updateWebhookApi,
            $this->webhookApi,
            $webhookUrlProvider,
            $this->webhookIdUpdater,
        );
    }

    public function test_it_implements_seller_webhook_event_types_registrar_interface(): void
    {
        self::assertInstanceOf(SellerWebhookEventTypesRegistrarInterface::class, $this->registrar);
    }

    public function test_it_points_the_registered_webhook_at_the_notify_url_and_subscribes_it_to_every_event_the_plugin_handles(): void
    {
        $this->webhookIdProvider->method('refresh')->with($this->paymentMethod)->willReturn('WEBHOOK_ID');

        $this->updateWebhookApi
            ->expects(self::once())
            ->method('update')
            ->with($this->paymentMethod, 'WEBHOOK_ID', 'https://shop.example.com/payment-methods/PAYPAL', WebhookApi::EVENT_TYPES)
        ;
        $this->webhookIdUpdater->expects(self::once())->method('update')->with($this->paymentMethod, 'WEBHOOK_ID');

        self::assertEquals(
            new WebhookRegistration('WEBHOOK_ID', 'https://shop.example.com/payment-methods/PAYPAL', false),
            $this->registrar->register($this->paymentMethod),
        );
    }

    public function test_it_registers_a_webhook_for_a_shop_that_has_none(): void
    {
        $this->webhookIdProvider->method('refresh')->willReturn(null);

        $this->updateWebhookApi->expects(self::never())->method('update');
        $this->webhookApi
            ->expects(self::once())
            ->method('register')
            ->with($this->paymentMethod, 'https://shop.example.com/payment-methods/PAYPAL')
            ->willReturn(['id' => 'WEBHOOK_ID'])
        ;
        $this->webhookIdUpdater->expects(self::once())->method('update')->with($this->paymentMethod, 'WEBHOOK_ID');

        self::assertEquals(
            new WebhookRegistration('WEBHOOK_ID', 'https://shop.example.com/payment-methods/PAYPAL', true),
            $this->registrar->register($this->paymentMethod),
        );
    }

    public function test_it_reports_a_registration_paypal_refused(): void
    {
        $this->webhookIdProvider->method('refresh')->willReturn(null);
        $this->webhookApi->method('register')->willThrowException(new PayPalApiRequestException('POST', 'v1/notifications/webhooks', 400, 'VALIDATION_ERROR', debugId: 'DEBUG_ID'));

        $this->expectException(PayPalWebhookNotRegisteredException::class);
        $this->expectExceptionMessage('"https://shop.example.com/payment-methods/PAYPAL": VALIDATION_ERROR (debug id: DEBUG_ID).');

        $this->registrar->register($this->paymentMethod);
    }

    public function test_it_reports_a_registration_paypal_answered_without_a_webhook_id(): void
    {
        $this->webhookIdProvider->method('refresh')->willReturn(null);
        $this->webhookApi->method('register')->willReturn([]);

        $this->webhookIdUpdater->expects(self::never())->method('update');

        $this->expectException(PayPalWebhookNotRegisteredException::class);
        $this->expectExceptionMessage('PayPal answered without a webhook id');

        $this->registrar->register($this->paymentMethod);
    }

    public function test_it_updates_rather_than_registers_when_a_webhook_already_exists(): void
    {
        $this->webhookIdProvider->method('refresh')->willReturn('WEBHOOK_ID');

        $this->webhookApi->expects(self::never())->method('register');

        $this->registrar->register($this->paymentMethod);
    }
}
