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

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Exception\OrderNotFoundException;

interface OrderProviderInterface
{
    /**
     * @throws OrderNotFoundException
     */
    public function provideOrderById(int $id): OrderInterface;

    /**
     * @throws OrderNotFoundException
     */
    public function provideOrderByToken(string $token): OrderInterface;
}
