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
use Sylius\Bundle\CoreBundle\OrderPay\Provider\UrlProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\Command\CapturePayPalOrderPaymentRequest;
use Sylius\PayPalPlugin\Command\CreatePayPalOrderPaymentRequest;
use Sylius\PayPalPlugin\CommandProvider\CapturePaymentRequestCommandProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CapturePaymentRequestCommandProviderTest extends TestCase
{
    private CapturePaymentRequestCommandProvider $commandProvider;

    protected function setUp(): void
    {
        $returnUrlProvider = $this->createMock(UrlProviderInterface::class);
        $returnUrlProvider
            ->method('getUrl')
            ->with(self::isInstanceOf(PaymentRequestInterface::class), UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://headless.example.com/paypal/return/PAYMENT_REQUEST_HASH')
        ;

        $this->commandProvider = new CapturePaymentRequestCommandProvider($returnUrlProvider);
    }

    public function test_it_supports_a_capture_payment_request(): void
    {
        $paymentRequest = $this->createStub(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_CAPTURE);

        self::assertTrue($this->commandProvider->supports($paymentRequest));
    }

    public function test_it_does_not_support_an_authorize_payment_request(): void
    {
        $paymentRequest = $this->createStub(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_AUTHORIZE);

        self::assertFalse($this->commandProvider->supports($paymentRequest));
    }

    public function test_it_provides_a_create_paypal_order_command_with_the_return_url_for_a_new_payment_request(): void
    {
        $paymentRequest = $this->createStub(PaymentRequestInterface::class);
        $paymentRequest->method('getId')->willReturn('PAYMENT_REQUEST_HASH');
        $paymentRequest->method('getState')->willReturn(PaymentRequestInterface::STATE_NEW);

        $command = $this->commandProvider->provide($paymentRequest);

        self::assertInstanceOf(CreatePayPalOrderPaymentRequest::class, $command);
        self::assertSame('PAYMENT_REQUEST_HASH', $command->getHash());
        self::assertSame('https://headless.example.com/paypal/return/PAYMENT_REQUEST_HASH', $command->getReturnUrl());
    }

    public function test_it_provides_a_capture_paypal_order_command_once_the_payer_has_approved_the_order(): void
    {
        $paymentRequest = $this->createStub(PaymentRequestInterface::class);
        $paymentRequest->method('getId')->willReturn('PAYMENT_REQUEST_HASH');
        $paymentRequest->method('getState')->willReturn(PaymentRequestInterface::STATE_PROCESSING);

        $command = $this->commandProvider->provide($paymentRequest);

        self::assertInstanceOf(CapturePayPalOrderPaymentRequest::class, $command);
        self::assertSame('PAYMENT_REQUEST_HASH', $command->getHash());
    }
}
