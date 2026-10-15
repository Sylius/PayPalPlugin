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

namespace Tests\Sylius\PayPalPlugin\Unit\Verifier;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Exception\ThreeDSecureAuthenticationFailedException;
use Sylius\PayPalPlugin\Verifier\PaymentThreeDSecureVerifier;
use Sylius\PayPalPlugin\Verifier\ThreeDSecureVerifierInterface;

final class PaymentThreeDSecureVerifierTest extends TestCase
{
    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private ThreeDSecureVerifierInterface&MockObject $threeDSecureVerifier;

    private PaymentThreeDSecureVerifier $verifier;

    protected function setUp(): void
    {
        $authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $authorizeClientApi->method('authorize')->willReturn('TOKEN');
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->threeDSecureVerifier = $this->createMock(ThreeDSecureVerifierInterface::class);

        $this->verifier = new PaymentThreeDSecureVerifier($authorizeClientApi, $this->orderDetailsApi, $this->threeDSecureVerifier);
    }

    public function test_it_verifies_the_3d_secure_result_paypal_holds_for_a_card_payment(): void
    {
        $this->orderDetailsApi->method('get')->with('TOKEN', 'PAYPAL_ORDER_ID')->willReturn(['id' => 'PAYPAL_ORDER_ID', 'payment_source' => ['card' => []]]);
        $this->threeDSecureVerifier->expects(self::once())->method('verify')->with(['id' => 'PAYPAL_ORDER_ID', 'payment_source' => ['card' => []]]);

        $this->verifier->verify($this->paymentWith('card'));
    }

    public function test_it_lets_the_refusal_of_the_3d_secure_result_through(): void
    {
        $this->orderDetailsApi->method('get')->willReturn([]);
        $this->threeDSecureVerifier->method('verify')->willThrowException(new ThreeDSecureAuthenticationFailedException(retryable: false));

        $this->expectException(ThreeDSecureAuthenticationFailedException::class);

        $this->verifier->verify($this->paymentWith('card'));
    }

    public function test_it_verifies_the_3d_secure_result_paypal_holds_for_a_google_pay_payment(): void
    {
        $this->orderDetailsApi->method('get')->willReturn(['id' => 'PAYPAL_ORDER_ID']);
        $this->threeDSecureVerifier->expects(self::once())->method('verify')->with(['id' => 'PAYPAL_ORDER_ID']);

        $this->verifier->verify($this->paymentWith('google_pay'));
    }

    /** @return iterable<string, array{string}> */
    public static function paymentSourcesWithout3DSecure(): iterable
    {
        yield 'paypal' => ['paypal'];
        yield 'venmo' => ['venmo'];
        yield 'apple_pay' => ['apple_pay'];
        yield 'trustly' => ['trustly'];
    }

    #[DataProvider('paymentSourcesWithout3DSecure')]
    public function test_it_asks_paypal_nothing_for_a_payment_source_without_3d_secure(string $paymentSource): void
    {
        $this->orderDetailsApi->expects(self::never())->method('get');
        $this->threeDSecureVerifier->expects(self::never())->method('verify');

        $this->verifier->verify($this->paymentWith($paymentSource));
    }

    private function paymentWith(string $paymentSource): PaymentInterface&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($this->createMock(PaymentMethodInterface::class));
        $payment->method('getDetails')->willReturn(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'payment_source' => $paymentSource]);

        return $payment;
    }
}
