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

namespace Sylius\PayPalPlugin\Remover;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Entity\PayPalCredentialsInterface;

final readonly class CachedAccessTokenRemover implements CachedAccessTokenRemoverInterface
{
    /** @param ObjectRepository<PayPalCredentialsInterface> $payPalCredentialsRepository */
    public function __construct(
        private ObjectManager $payPalCredentialsManager,
        private ObjectRepository $payPalCredentialsRepository,
    ) {
    }

    public function remove(PaymentMethodInterface $paymentMethod): void
    {
        $payPalCredentials = $this->payPalCredentialsRepository->findOneBy(['paymentMethod' => $paymentMethod]);
        if (null === $payPalCredentials) {
            return;
        }

        $this->payPalCredentialsManager->remove($payPalCredentials);
    }
}
