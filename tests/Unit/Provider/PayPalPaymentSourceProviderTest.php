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

namespace Tests\Sylius\PayPalPlugin\Unit\Provider;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Exception\UnsupportedPayPalPaymentSourceException;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProvider;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;

final class PayPalPaymentSourceProviderTest extends TestCase
{
    private OrderInterface&MockObject $order;

    private PayPalPaymentSourceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->order = $this->createMock(OrderInterface::class);
        $this->provider = new PayPalPaymentSourceProvider();
    }

    public function test_it_implements_paypal_payment_source_provider_interface(): void
    {
        self::assertInstanceOf(PayPalPaymentSourceProviderInterface::class, $this->provider);
    }

    public function test_it_wraps_the_experience_context_in_the_paypal_payment_source(): void
    {
        $experienceContext = ['locale' => 'en-US', 'user_action' => 'PAY_NOW'];

        self::assertSame(
            ['paypal' => ['experience_context' => $experienceContext]],
            $this->provider->provide($this->order, PayPalPaymentSourceProviderInterface::PAYPAL, $experienceContext),
        );
    }

    public function test_it_supports_the_paypal_payment_source(): void
    {
        self::assertTrue($this->provider->supports(PayPalPaymentSourceProviderInterface::PAYPAL));
    }

    public function test_it_does_not_support_an_unknown_payment_source(): void
    {
        self::assertFalse($this->provider->supports('bitcoin'));
    }

    public function test_it_throws_an_exception_when_the_payment_source_is_not_supported(): void
    {
        $this->expectException(UnsupportedPayPalPaymentSourceException::class);
        $this->expectExceptionMessage('PayPal payment source "bitcoin" is not supported');

        $this->provider->provide($this->order, 'bitcoin', []);
    }
}
