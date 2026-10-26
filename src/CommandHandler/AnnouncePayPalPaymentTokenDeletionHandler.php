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

namespace Sylius\PayPalPlugin\CommandHandler;

use Sylius\PayPalPlugin\Command\AnnouncePayPalPaymentTokenDeletion;
use Sylius\PayPalPlugin\Event\PayPalPaymentTokenDeleted;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class AnnouncePayPalPaymentTokenDeletionHandler
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function __invoke(AnnouncePayPalPaymentTokenDeletion $command): void
    {
        $this->eventDispatcher->dispatch(new PayPalPaymentTokenDeleted($command->vaultId));
    }
}
