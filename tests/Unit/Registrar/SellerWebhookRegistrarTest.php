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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\WebhookApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookAlreadyRegisteredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrar;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;
use Sylius\PayPalPlugin\Updater\WebhookIdUpdaterInterface;

final class SellerWebhookRegistrarTest extends TestCase
{
    private WebhookApiInterface&MockObject $webhookApi;

    private WebhookIdUpdaterInterface&MockObject $webhookIdUpdater;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private SellerWebhookRegistrar $sellerWebhookRegistrar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webhookApi = $this->createMock(WebhookApiInterface::class);
        $this->webhookIdUpdater = $this->createMock(WebhookIdUpdaterInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $webhookUrlProvider = $this->createMock(WebhookUrlProviderInterface::class);
        $webhookUrlProvider->method('provide')->with($this->paymentMethod)->willReturn('https://webhook-url.com');

        $this->sellerWebhookRegistrar = new SellerWebhookRegistrar(
            $this->webhookApi,
            $webhookUrlProvider,
            $this->webhookIdUpdater,
        );
    }

    #[Test]
    public function it_implements_seller_webhook_registrar_interface(): void
    {
        self::assertInstanceOf(SellerWebhookRegistrarInterface::class, $this->sellerWebhookRegistrar);
    }

    public function test_it_registers_the_webhook_and_stores_its_id(): void
    {
        $this->webhookApi
            ->expects(self::once())
            ->method('register')
            ->with($this->paymentMethod, 'https://webhook-url.com')
            ->willReturn(['id' => 'WH-123', 'url' => 'https://webhook-url.com']);
        $this->webhookIdUpdater->expects(self::once())->method('update')->with($this->paymentMethod, 'WH-123');

        $this->sellerWebhookRegistrar->register($this->paymentMethod);
    }

    public function test_it_stores_no_id_when_paypal_answers_without_one(): void
    {
        $this->webhookApi->method('register')->willReturn([]);
        $this->webhookIdUpdater->expects(self::never())->method('update');

        $this->sellerWebhookRegistrar->register($this->paymentMethod);
    }

    #[Test]
    public function it_throws_exception_if_webhook_could_not_be_registered(): void
    {
        $this->webhookApi->method('register')->willThrowException($this->rejection('VALIDATION_ERROR'));
        $this->webhookIdUpdater->expects(self::never())->method('update');

        $this->expectException(PayPalWebhookUrlNotValidException::class);

        $this->sellerWebhookRegistrar->register($this->paymentMethod);
    }

    public function test_it_throws_an_exception_if_the_webhook_is_already_registered(): void
    {
        $this->webhookApi->method('register')->willThrowException($this->rejection('WEBHOOK_URL_ALREADY_EXISTS'));
        $this->webhookIdUpdater->expects(self::never())->method('update');

        $this->expectException(PayPalWebhookAlreadyRegisteredException::class);

        $this->sellerWebhookRegistrar->register($this->paymentMethod);
    }

    public function test_it_leaves_the_webhook_unregistered_when_paypal_fails_for_another_reason(): void
    {
        $this->webhookApi->expects(self::once())->method('register')->willThrowException($this->rejection('WEBHOOK_NUMBER_LIMIT_EXCEEDED'));
        $this->webhookIdUpdater->expects(self::never())->method('update');

        $this->sellerWebhookRegistrar->register($this->paymentMethod);
    }

    private function rejection(string $name): PayPalApiRequestException
    {
        return new PayPalApiRequestException('POST', 'v1/notifications/webhooks', 400, $name);
    }
}
