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

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\Command\AnnouncePayPalPaymentToken;
use Sylius\PayPalPlugin\Event\PayPalPaymentTokenCreated;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class AnnouncePayPalPaymentTokenHandler
{
    /** @param PaymentRepositoryInterface<PaymentInterface> $paymentRepository */
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(AnnouncePayPalPaymentToken $command): void
    {
        $payment = $this->paymentRepository->find($command->paymentId);
        if (!$payment instanceof PaymentInterface) {
            return;
        }

        if ($command->vaultId !== PayPalPaymentDetails::fromPayment($payment)->vaultId()) {
            return;
        }

        $this->eventDispatcher->dispatch(new PayPalPaymentTokenCreated($payment, $command->vaultId));
    }
}
