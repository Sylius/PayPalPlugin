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

namespace Sylius\PayPalPlugin\Controller;

use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final readonly class CancelPayPalOrderAction
{
    public function __construct(private RequestStack $flashBagOrRequestStack)
    {
    }

    public function __invoke(Request $request): Response
    {
        FlashBagProvider::getFlashBag($this->flashBagOrRequestStack)->add('success', 'sylius_paypal.order_cancelled');

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
