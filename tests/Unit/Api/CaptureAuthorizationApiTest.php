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
use Sylius\PayPalPlugin\Api\CaptureAuthorizationApi;
use Sylius\PayPalPlugin\Api\CaptureAuthorizationApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class CaptureAuthorizationApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private CaptureAuthorizationApi $captureAuthorizationApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->captureAuthorizationApi = new CaptureAuthorizationApi($this->client);
    }

    public function test_it_implements_capture_authorization_api_interface(): void
    {
        self::assertInstanceOf(CaptureAuthorizationApiInterface::class, $this->captureAuthorizationApi);
    }

    public function test_it_captures_the_whole_authorization_as_the_final_capture(): void
    {
        $this->client
            ->expects(self::once())
            ->method('post')
            ->with($this->paymentMethod, 'v2/payments/authorizations/AUTHORIZATION_ID/capture', ['final_capture' => true], [])
            ->willReturn(['id' => 'CAPTURE_ID', 'status' => 'COMPLETED'])
        ;

        self::assertSame(['id' => 'CAPTURE_ID', 'status' => 'COMPLETED'], $this->captureAuthorizationApi->capture($this->paymentMethod, 'AUTHORIZATION_ID'));
    }

    public function test_it_captures_the_authorization_under_the_request_id_given_by_the_caller(): void
    {
        $this->client
            ->expects(self::once())
            ->method('post')
            ->with($this->paymentMethod, 'v2/payments/authorizations/AUTHORIZATION_ID/capture', ['final_capture' => true], ['PayPal-Request-Id' => 'PAYMENT_REQUEST_HASH'])
            ->willReturn(['id' => 'CAPTURE_ID', 'status' => 'COMPLETED'])
        ;

        $this->captureAuthorizationApi->capture($this->paymentMethod, 'AUTHORIZATION_ID', 'PAYMENT_REQUEST_HASH');
    }
}
