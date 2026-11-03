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

namespace Tests\Sylius\PayPalPlugin\Unit\Api;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\AuthorizeOrderApi;
use Sylius\PayPalPlugin\Api\AuthorizeOrderApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class AuthorizeOrderApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private AuthorizeOrderApi $authorizeOrderApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->authorizeOrderApi = new AuthorizeOrderApi($this->client);
    }

    public function test_it_implements_authorize_order_api_interface(): void
    {
        self::assertInstanceOf(AuthorizeOrderApiInterface::class, $this->authorizeOrderApi);
    }

    public function test_it_authorizes_the_paypal_order_with_the_given_id(): void
    {
        $this->client
            ->expects(self::once())
            ->method('post')
            ->with($this->paymentMethod, 'v2/checkout/orders/123123/authorize', null, [])
            ->willReturn(['status' => 'COMPLETED', 'id' => '123123'])
        ;

        self::assertSame(['status' => 'COMPLETED', 'id' => '123123'], $this->authorizeOrderApi->authorize($this->paymentMethod, '123123'));
    }

    public function test_it_authorizes_the_paypal_order_under_the_request_id_given_by_the_caller(): void
    {
        $this->client
            ->expects(self::once())
            ->method('post')
            ->with($this->paymentMethod, 'v2/checkout/orders/123123/authorize', null, ['PayPal-Request-Id' => 'PAYMENT_REQUEST_HASH-authorize'])
            ->willReturn(['status' => 'COMPLETED'])
        ;

        $this->authorizeOrderApi->authorize($this->paymentMethod, '123123', 'PAYMENT_REQUEST_HASH-authorize');
    }
}
