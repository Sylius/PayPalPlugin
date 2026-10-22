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
use Sylius\PayPalPlugin\Api\VoidAuthorizationApi;
use Sylius\PayPalPlugin\Api\VoidAuthorizationApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class VoidAuthorizationApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private VoidAuthorizationApi $voidAuthorizationApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->voidAuthorizationApi = new VoidAuthorizationApi($this->client);
    }

    public function test_it_implements_void_authorization_api_interface(): void
    {
        self::assertInstanceOf(VoidAuthorizationApiInterface::class, $this->voidAuthorizationApi);
    }

    public function test_it_voids_the_authorization_with_the_given_id(): void
    {
        $this->client
            ->expects(self::once())
            ->method('post')
            ->with($this->paymentMethod, 'v2/payments/authorizations/AUTHORIZATION_ID/void', null, [])
            ->willReturn([])
        ;

        self::assertSame([], $this->voidAuthorizationApi->void($this->paymentMethod, 'AUTHORIZATION_ID'));
    }

    public function test_it_voids_the_authorization_under_the_request_id_given_by_the_caller(): void
    {
        $this->client
            ->expects(self::once())
            ->method('post')
            ->with($this->paymentMethod, 'v2/payments/authorizations/AUTHORIZATION_ID/void', null, ['PayPal-Request-Id' => 'PAYMENT_REQUEST_HASH'])
            ->willReturn([])
        ;

        $this->voidAuthorizationApi->void($this->paymentMethod, 'AUTHORIZATION_ID', 'PAYMENT_REQUEST_HASH');
    }
}
