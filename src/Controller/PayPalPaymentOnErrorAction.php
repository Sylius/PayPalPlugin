<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class PayPalPaymentOnErrorAction
{
    /** @var FlashBagInterface */
    private $flashBag;

    public function __construct(FlashBagInterface $flashBag)
    {
        $this->flashBag = $flashBag;
    }

    public function __invoke(Request $request): Response
    {
        // TODO log error somewhere
        $this->flashBag->add('error', 'sylius.pay_pal.something_went_wrong');

        return new Response();
    }
}
