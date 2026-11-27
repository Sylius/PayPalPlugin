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

namespace Tests\Sylius\PayPalPlugin\Unit\Enabler;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Enabler\PaymentMethodEnablerInterface;
use Sylius\PayPalPlugin\Enabler\PayPalPaymentMethodEnabler;
use Sylius\PayPalPlugin\Exception\PaymentMethodCouldNotBeEnabledException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookAlreadyRegisteredException;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;

final class PayPalPaymentMethodEnablerTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private RequestFactoryInterface&MockObject $requestFactory;

    private ObjectManager&MockObject $paymentMethodManager;

    private SellerWebhookRegistrarInterface&MockObject $sellerWebhookRegistrar;

    private PayPalPaymentMethodEnabler $payPalPaymentMethodEnabler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->paymentMethodManager = $this->createMock(ObjectManager::class);
        $this->sellerWebhookRegistrar = $this->createMock(SellerWebhookRegistrarInterface::class);

        $this->payPalPaymentMethodEnabler = new PayPalPaymentMethodEnabler(
            $this->client,
            'http://base-url.com',
            $this->paymentMethodManager,
            $this->sellerWebhookRegistrar,
            $this->requestFactory,
        );
    }

    #[Test]
    public function it_implements_payment_method_enabler_interface(): void
    {
        self::assertInstanceOf(PaymentMethodEnablerInterface::class, $this->payPalPaymentMethodEnabler);
    }

    #[Test]
    public function it_enables_payment_method_if_it_has_proper_credentials_and_webhook_are_set(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->expects(self::once())
            ->method('getConfig')
            ->willReturn(['merchant_id' => '123123', 'client_id' => 'CLIENT-ID', 'client_secret' => 'SECRET']);

        $this->requestFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with('GET', 'http://base-url.com/seller-permissions/check/123123')
            ->willReturn($request);

        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $response
            ->expects(self::once())
            ->method('getBody')
            ->willReturn($body);

        $body
            ->expects(self::once())
            ->method('getContents')
            ->willReturn('{ "permissionsGranted": true }');

        $this->sellerWebhookRegistrar
            ->expects(self::once())
            ->method('register')
            ->with($paymentMethod);

        $paymentMethod
            ->expects(self::once())
            ->method('setEnabled')
            ->with(true);

        $this->paymentMethodManager
            ->expects(self::once())
            ->method('flush');

        $this->payPalPaymentMethodEnabler->enable($paymentMethod);
    }

    #[Test]
    public function it_throws_exception_if_payment_method_credentials_are_not_granted(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->expects(self::once())
            ->method('getConfig')
            ->willReturn(['merchant_id' => '123123', 'client_id' => 'CLIENT-ID', 'client_secret' => 'SECRET']);

        $this->requestFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with('GET', 'http://base-url.com/seller-permissions/check/123123')
            ->willReturn($request);

        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $response
            ->expects(self::once())
            ->method('getBody')
            ->willReturn($body);

        $body
            ->expects(self::once())
            ->method('getContents')
            ->willReturn('{ "permissionsGranted": false }');

        $this->sellerWebhookRegistrar
            ->expects($this->never())
            ->method('register');

        $paymentMethod
            ->expects($this->never())
            ->method('setEnabled');

        $this->paymentMethodManager
            ->expects($this->never())
            ->method('flush');

        $this->expectException(PaymentMethodCouldNotBeEnabledException::class);

        $this->payPalPaymentMethodEnabler->enable($paymentMethod);
    }

    #[Test]
    public function it_enables_a_payment_method_created_with_sandbox_credentials_without_asking_the_facilitator(): void
    {
        $paymentMethod = $this->paymentMethod([
            'merchant_id' => '123123',
            'client_id' => 'CLIENT-ID',
            'client_secret' => 'SECRET',
            'sylius_merchant_id' => 'SYLIUS_SANDBOX_MERCHANT_ID',
        ]);

        $this->client->expects(self::never())->method('sendRequest');
        $this->sellerWebhookRegistrar->expects(self::once())->method('register')->with($paymentMethod);
        $paymentMethod->expects(self::once())->method('setEnabled')->with(true);
        $this->paymentMethodManager->expects(self::once())->method('flush');

        $this->payPalPaymentMethodEnabler->enable($paymentMethod);
    }

    #[Test]
    public function it_enables_a_payment_method_whose_webhook_is_already_registered(): void
    {
        $paymentMethod = $this->paymentMethod([
            'merchant_id' => '123123',
            'client_id' => 'CLIENT-ID',
            'client_secret' => 'SECRET',
            'sylius_merchant_id' => 'SYLIUS_SANDBOX_MERCHANT_ID',
        ]);

        $this->sellerWebhookRegistrar->method('register')->willThrowException(new PayPalWebhookAlreadyRegisteredException());
        $paymentMethod->expects(self::once())->method('setEnabled')->with(true);
        $this->paymentMethodManager->expects(self::once())->method('flush');

        $this->payPalPaymentMethodEnabler->enable($paymentMethod);
    }

    /** @param array<string, mixed> $config */
    private function paymentMethod(array $config): PaymentMethodInterface&MockObject
    {
        $gatewayConfig = $this->createStub(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn($config);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        return $paymentMethod;
    }
}
