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

namespace Tests\Sylius\PayPalPlugin\Unit\Entity;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Entity\PayPalCredentials;
use Sylius\PayPalPlugin\Entity\PayPalCredentialsInterface;

final class PayPalCredentialsTest extends TestCase
{
    private PaymentMethodInterface $paymentMethod;

    private PayPalCredentials $payPalCredentials;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->payPalCredentials = new PayPalCredentials(
            '123ASD123',
            $this->paymentMethod,
            'TOKEN',
            new \DateTime('2020-01-01 10:00:00'),
            3600,
        );
    }

    #[Test]
    public function it_implements_paypal_credentials_interface(): void
    {
        self::assertInstanceOf(PayPalCredentialsInterface::class, $this->payPalCredentials);
    }

    #[Test]
    public function it_has_a_payment_method(): void
    {
        $result = $this->payPalCredentials->paymentMethod();

        self::assertEquals($this->paymentMethod, $result);
    }

    #[Test]
    public function it_has_a_access_token(): void
    {
        $result = $this->payPalCredentials->accessToken();

        self::assertEquals('TOKEN', $result);
    }

    #[Test]
    public function it_has_a_creation_time(): void
    {
        $result = $this->payPalCredentials->creationTime();

        self::assertEquals(new \DateTime('2020-01-01 10:00:00'), $result);
    }

    #[Test]
    public function it_has_a_expiration_time(): void
    {
        $result = $this->payPalCredentials->expirationTime();

        self::assertEquals(new \DateTime('2020-01-01 11:00:00'), $result);
    }

    public function test_it_is_expired_from_its_expiration_time(): void
    {
        self::assertFalse($this->payPalCredentials->isExpired(new \DateTime('2020-01-01 10:59:59')));
        self::assertTrue($this->payPalCredentials->isExpired(new \DateTime('2020-01-01 11:00:00')));
    }

    public function test_it_is_refreshed_with_a_new_token(): void
    {
        $this->payPalCredentials->refresh('NEW_TOKEN', new \DateTime('2020-01-01 12:00:00'), 600);

        self::assertSame('NEW_TOKEN', $this->payPalCredentials->accessToken());
        self::assertEquals(new \DateTime('2020-01-01 12:00:00'), $this->payPalCredentials->creationTime());
        self::assertEquals(new \DateTime('2020-01-01 12:10:00'), $this->payPalCredentials->expirationTime());
    }

    public function test_it_can_be_expired_before_its_time(): void
    {
        $this->payPalCredentials->expire();

        self::assertTrue($this->payPalCredentials->isExpired(new \DateTime('2020-01-01 10:00:00')));
    }
}
