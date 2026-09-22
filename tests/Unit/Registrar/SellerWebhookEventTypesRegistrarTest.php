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
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\UpdateWebhookApiInterface;
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Exception\PayPalWebhookNotRegisteredException;
use Sylius\PayPalPlugin\Provider\WebhookIdProviderInterface;
use Sylius\PayPalPlugin\Registrar\SellerWebhookEventTypesRegistrar;
use Sylius\PayPalPlugin\Registrar\SellerWebhookEventTypesRegistrarInterface;

final class SellerWebhookEventTypesRegistrarTest extends TestCase
{
    private WebhookIdProviderInterface&MockObject $webhookIdProvider;

    private CacheAuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private UpdateWebhookApiInterface&MockObject $updateWebhookApi;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private SellerWebhookEventTypesRegistrar $registrar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webhookIdProvider = $this->createMock(WebhookIdProviderInterface::class);
        $this->authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $this->updateWebhookApi = $this->createMock(UpdateWebhookApiInterface::class);

        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->paymentMethod->method('getCode')->willReturn('PAYPAL');
        $this->authorizeClientApi->method('authorize')->willReturn('TOKEN');

        $this->registrar = new SellerWebhookEventTypesRegistrar(
            $this->webhookIdProvider,
            $this->authorizeClientApi,
            $this->updateWebhookApi,
        );
    }

    public function test_it_implements_seller_webhook_event_types_registrar_interface(): void
    {
        self::assertInstanceOf(SellerWebhookEventTypesRegistrarInterface::class, $this->registrar);
    }

    public function test_it_subscribes_the_registered_webhook_to_every_event_the_plugin_handles(): void
    {
        $this->webhookIdProvider->method('refresh')->with($this->paymentMethod)->willReturn('WEBHOOK_ID');

        $this->updateWebhookApi
            ->expects(self::once())
            ->method('updateEventTypes')
            ->with('TOKEN', 'WEBHOOK_ID', WebhookApi::EVENT_TYPES)
        ;

        $this->registrar->register($this->paymentMethod);
    }

    public function test_it_refuses_to_update_a_webhook_paypal_does_not_have(): void
    {
        $this->webhookIdProvider->method('refresh')->willReturn(null);

        $this->updateWebhookApi->expects(self::never())->method('updateEventTypes');

        $this->expectException(PayPalWebhookNotRegisteredException::class);
        $this->expectExceptionMessage('PayPal has no webhook registered for the payment method "PAYPAL".');

        $this->registrar->register($this->paymentMethod);
    }
}
