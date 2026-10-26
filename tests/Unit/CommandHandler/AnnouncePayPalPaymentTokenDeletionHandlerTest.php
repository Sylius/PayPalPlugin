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

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Command\AnnouncePayPalPaymentTokenDeletion;
use Sylius\PayPalPlugin\CommandHandler\AnnouncePayPalPaymentTokenDeletionHandler;
use Sylius\PayPalPlugin\Event\PayPalPaymentTokenDeleted;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class AnnouncePayPalPaymentTokenDeletionHandlerTest extends TestCase
{
    public function test_it_announces_the_deleted_vault_token(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (PayPalPaymentTokenDeleted $event): bool => 'VAULT_ID' === $event->vaultId))
            ->willReturnArgument(0)
        ;

        (new AnnouncePayPalPaymentTokenDeletionHandler($eventDispatcher))(new AnnouncePayPalPaymentTokenDeletion('VAULT_ID'));
    }
}
