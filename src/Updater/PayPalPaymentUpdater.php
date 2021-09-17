<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Updater;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\PaymentInterface;

final class PayPalPaymentUpdater implements PaymentUpdaterInterface
{
    private ObjectManager $paymentManager;

    public function __construct(ObjectManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    public function updateAmount(PaymentInterface $payment, int $newAmount): void
    {
        $payment->setAmount($newAmount);

        $this->paymentManager->flush();
    }
}
