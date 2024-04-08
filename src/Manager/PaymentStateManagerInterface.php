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

namespace Sylius\PayPalPlugin\Manager;

use Sylius\Component\Core\Model\PaymentInterface;

interface PaymentStateManagerInterface
{
    public function create(PaymentInterface $payment): void;

    public function process(PaymentInterface $payment): void;

    public function complete(PaymentInterface $payment): void;

    public function cancel(PaymentInterface $payment): void;
}
