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

namespace Sylius\PayPalPlugin\Provider;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class FlashBagProvider
{
    public static function getFlashBag(FlashBagInterface|RequestStack $flashBagOrRequestStack): FlashBagInterface
    {
        if ($flashBagOrRequestStack instanceof FlashBagInterface) {
            return $flashBagOrRequestStack;
        }

        /** @var FlashBagInterface $flashBag */
        $flashBag = $flashBagOrRequestStack->getSession()->getBag('flashes');

        return $flashBag;
    }
}
