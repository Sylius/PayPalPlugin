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
use Sylius\PayPalPlugin\Command\RefundPayPalLateCapturePaymentRequest;
use Sylius\PayPalPlugin\CommandProvider\RefundLateCapturePaymentRequestCommandProvider;

final class RefundLateCapturePaymentRequestCommandProviderTest extends TestCase
{
    public function test_it_supports_the_late_capture_refund_action_only(): void
    {
        $provider = new RefundLateCapturePaymentRequestCommandProvider();

        self::assertTrue($provider->supports($this->paymentRequest('refund_late_capture')));
        self::assertFalse($provider->supports($this->paymentRequest(PaymentRequestInterface::ACTION_REFUND)));
    }

    public function test_it_provides_the_late_capture_refund_command_for_the_request(): void
    {
        $command = (new RefundLateCapturePaymentRequestCommandProvider())->provide($this->paymentRequest('refund_late_capture'));

        self::assertInstanceOf(RefundPayPalLateCapturePaymentRequest::class, $command);
        self::assertSame('PAYMENT_REQUEST_HASH', $command->getHash());
    }

    private function paymentRequest(string $action): PaymentRequestInterface
    {
        $paymentRequest = $this->createStub(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn($action);
        $paymentRequest->method('getId')->willReturn('PAYMENT_REQUEST_HASH');

        return $paymentRequest;
    }
}
