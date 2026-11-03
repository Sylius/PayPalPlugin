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

namespace Tests\Sylius\PayPalPlugin\Unit\CommandHandler;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\Command\AnnouncePayPalPaymentToken;
use Sylius\PayPalPlugin\CommandHandler\AnnouncePayPalPaymentTokenHandler;
use Sylius\PayPalPlugin\Event\PayPalPaymentTokenCreated;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class AnnouncePayPalPaymentTokenHandlerTest extends TestCase
{
    private PaymentRepositoryInterface&MockObject $paymentRepository;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private AnnouncePayPalPaymentTokenHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->handler = new AnnouncePayPalPaymentTokenHandler($this->paymentRepository, $this->eventDispatcher);
    }

    public function test_it_announces_the_vault_token_the_payment_carries(): void
    {
        $payment = $this->paymentWith(['vault_id' => 'VAULT_ID']);
        $this->paymentRepository->method('find')->with(7)->willReturn($payment);

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (PayPalPaymentTokenCreated $event): bool => $payment === $event->payment && 'VAULT_ID' === $event->vaultId))
            ->willReturnArgument(0)
        ;

        ($this->handler)(new AnnouncePayPalPaymentToken(7, 'VAULT_ID'));
    }

    public function test_it_does_not_announce_a_vault_token_the_payment_no_longer_carries(): void
    {
        $this->paymentRepository->method('find')->willReturn($this->paymentWith(['vault_id' => 'OTHER_VAULT_ID']));

        $this->eventDispatcher->expects(self::never())->method('dispatch');

        ($this->handler)(new AnnouncePayPalPaymentToken(7, 'VAULT_ID'));
    }

    public function test_it_does_not_announce_the_vault_token_of_a_payment_that_is_gone(): void
    {
        $this->paymentRepository->method('find')->willReturn(null);

        $this->eventDispatcher->expects(self::never())->method('dispatch');

        ($this->handler)(new AnnouncePayPalPaymentToken(7, 'VAULT_ID'));
    }

    /** @param array<string, mixed> $details */
    private function paymentWith(array $details): PaymentInterface&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn($details);

        return $payment;
    }
}
