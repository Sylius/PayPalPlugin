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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\RefundPaymentApi;
use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class RefundPaymentApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private RefundPaymentApi $refundPaymentApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->refundPaymentApi = new RefundPaymentApi($this->client);
    }

    #[Test]
    public function it_implements_refund_order_api_interface(): void
    {
        self::assertInstanceOf(RefundPaymentApiInterface::class, $this->refundPaymentApi);
    }

    #[Test]
    public function it_refunds_paypal_payment_with_given_id(): void
    {
        $this->client
            ->expects(self::once())
            ->method('post')
            ->with(
                $this->paymentMethod,
                'v2/payments/captures/123123/refund',
                ['amount' => ['value' => '10.99', 'currency_code' => 'USD'], 'invoice_id' => '123-11-11-2010'],
                ['PayPal-Auth-Assertion' => 'PAY-PAL-AUTH-ASSERTION'],
            )
            ->willReturn(['status' => 'COMPLETED', 'id' => '123123']);

        $result = $this->refundPaymentApi->refund($this->paymentMethod, '123123', 'PAY-PAL-AUTH-ASSERTION', '123-11-11-2010', '10.99', 'USD');

        self::assertEquals(['status' => 'COMPLETED', 'id' => '123123'], $result);
    }

    #[Test]
    public function it_refunds_paypal_payment_under_given_request_id(): void
    {
        $this->client
            ->expects(self::once())
            ->method('post')
            ->with(
                $this->paymentMethod,
                'v2/payments/captures/123123/refund',
                ['amount' => ['value' => '10.99', 'currency_code' => 'USD'], 'invoice_id' => '123-11-11-2010'],
                ['PayPal-Auth-Assertion' => 'PAY-PAL-AUTH-ASSERTION', 'PayPal-Request-Id' => 'REQUEST-ID'],
            )
            ->willReturn(['status' => 'COMPLETED', 'id' => '123123']);

        $this->refundPaymentApi->refund($this->paymentMethod, '123123', 'PAY-PAL-AUTH-ASSERTION', '123-11-11-2010', '10.99', 'USD', 'REQUEST-ID');
    }
}
