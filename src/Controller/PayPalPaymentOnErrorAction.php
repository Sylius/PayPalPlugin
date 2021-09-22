<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class PayPalPaymentOnErrorAction
{
    private FlashBagInterface $flashBag;

    private LoggerInterface $logger;

    public function __construct(FlashBagInterface $flashBag, LoggerInterface $logger)
    {
        $this->flashBag = $flashBag;
        $this->logger = $logger;
    }

    public function __invoke(Request $request): Response
    {
        $this->logger->error((string) $request->getContent());
        $this->flashBag->add('error', 'sylius.pay_pal.something_went_wrong');

        return new Response();
    }
}
