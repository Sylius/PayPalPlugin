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
use Sylius\PayPalPlugin\Api\CompleteOrderApi;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class CompleteOrderApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private CompleteOrderApi $completeOrderApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->completeOrderApi = new CompleteOrderApi($this->client);
    }

    #[Test]
    public function it_implements_complete_order_api_interface(): void
    {
        self::assertInstanceOf(CompleteOrderApiInterface::class, $this->completeOrderApi);
    }

    #[Test]
    public function it_completes_paypal_order_with_given_id(): void
    {
        $this->client
            ->expects(self::once())
            ->method('post')
            ->with($this->paymentMethod, 'v2/checkout/orders/123123/capture')
            ->willReturn(['status' => 'COMPLETED', 'id' => 123]);

        $result = $this->completeOrderApi->complete($this->paymentMethod, '123123');

        self::assertEquals(['status' => 'COMPLETED', 'id' => 123], $result);
    }
}
