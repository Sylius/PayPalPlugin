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

namespace Tests\Sylius\PayPalPlugin\Unit\CommandProvider;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\Command\CapturePaymentRequest;
use Sylius\PayPalPlugin\CommandProvider\CapturePaymentRequestCommandProvider;

final class CapturePaymentRequestCommandProviderTest extends TestCase
{
    public function test_it_supports_a_capture_payment_request(): void
    {
        $paymentRequest = $this->createStub(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_CAPTURE);

        self::assertTrue((new CapturePaymentRequestCommandProvider())->supports($paymentRequest));
    }

    public function test_it_does_not_support_an_authorize_payment_request(): void
    {
        $paymentRequest = $this->createStub(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_AUTHORIZE);

        self::assertFalse((new CapturePaymentRequestCommandProvider())->supports($paymentRequest));
    }

    public function test_it_provides_a_capture_command_for_the_payment_request(): void
    {
        $paymentRequest = $this->createStub(PaymentRequestInterface::class);
        $paymentRequest->method('getId')->willReturn('PAYMENT_REQUEST_HASH');

        $command = (new CapturePaymentRequestCommandProvider())->provide($paymentRequest);

        self::assertInstanceOf(CapturePaymentRequest::class, $command);
        self::assertSame('PAYMENT_REQUEST_HASH', $command->getHash());
    }
}
