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
use Sylius\PayPalPlugin\Command\CaptureAuthorizationPaymentRequest;
use Sylius\PayPalPlugin\CommandProvider\CaptureAuthorizationPaymentRequestCommandProvider;

final class CaptureAuthorizationPaymentRequestCommandProviderTest extends TestCase
{
    public function test_it_supports_the_capture_authorization_action_only(): void
    {
        $provider = new CaptureAuthorizationPaymentRequestCommandProvider();

        self::assertTrue($provider->supports($this->paymentRequest('capture_authorization')));
        self::assertFalse($provider->supports($this->paymentRequest(PaymentRequestInterface::ACTION_CAPTURE)));
    }

    public function test_it_provides_the_capture_authorization_command_for_the_request(): void
    {
        $command = (new CaptureAuthorizationPaymentRequestCommandProvider())->provide($this->paymentRequest('capture_authorization'));

        self::assertInstanceOf(CaptureAuthorizationPaymentRequest::class, $command);
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
