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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Provider\InvoiceNumberProvider;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;

final class InvoiceNumberProviderTest extends TestCase
{
    private PaymentReferenceNumberProviderInterface&MockObject $paymentReferenceNumberProvider;

    private InvoiceNumberProvider $invoiceNumberProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentReferenceNumberProvider = $this->createMock(PaymentReferenceNumberProviderInterface::class);
        $this->invoiceNumberProvider = new InvoiceNumberProvider($this->paymentReferenceNumberProvider);
    }

    #[Test]
    public function it_appends_the_reference_id_to_the_payment_reference_number(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->paymentReferenceNumberProvider
            ->method('provide')
            ->with($payment)
            ->willReturn('1-01-01-2026-12-00-00');

        self::assertSame(
            '1-01-01-2026-12-00-00-REFERENCE_ID',
            $this->invoiceNumberProvider->provide($payment, 'REFERENCE_ID'),
        );
    }
}
