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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Api\AuthorizeClientApi;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Exception\PayPalAuthorizationException;

final class AuthorizeClientApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $payPalClient;

    private AuthorizeClientApi $authorizeClientApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payPalClient = $this->createMock(PayPalClientInterface::class);
        $this->authorizeClientApi = new AuthorizeClientApi($this->payPalClient);
    }

    #[Test]
    public function it_implements_authorize_client_api_interface(): void
    {
        self::assertInstanceOf(AuthorizeClientApiInterface::class, $this->authorizeClientApi);
    }

    public function test_it_returns_the_access_token_with_the_lifetime_paypal_gives_it(): void
    {
        $this->payPalClient
            ->method('authorize')
            ->with('CLIENT_ID', 'CLIENT_SECRET', true)
            ->willReturn(['access_token' => 'TOKEN', 'expires_in' => '32400'])
        ;

        $accessToken = $this->authorizeClientApi->authorize('CLIENT_ID', 'CLIENT_SECRET', true);

        self::assertSame('TOKEN', $accessToken->value());
        self::assertSame(32400, $accessToken->expiresIn());
    }

    public function test_it_throws_an_exception_if_paypal_returns_no_access_token(): void
    {
        $this->payPalClient->method('authorize')->willReturn(['error' => 'invalid_client']);

        $this->expectException(PayPalAuthorizationException::class);

        $this->authorizeClientApi->authorize('CLIENT_ID', 'CLIENT_SECRET', false);
    }
}
