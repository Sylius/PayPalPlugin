<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class PayPalPaymentOnErrorAction
{
    private FlashBagInterface|RequestStack $flashBagOrRequestStack;

    private LoggerInterface $logger;

    public function __construct(FlashBagInterface|RequestStack $flashBagOrRequestStack, LoggerInterface $logger)
    {
        $this->flashBagOrRequestStack = $flashBagOrRequestStack;
        $this->logger = $logger;
    }

    public function __invoke(Request $request): Response
    {
        $this->logger->error((string) $request->getContent());
        FlashBagProvider::getFlashBag($this->flashBagOrRequestStack)
            ->add('error', 'sylius.pay_pal.something_went_wrong')
        ;

        return new Response();
    }
}
