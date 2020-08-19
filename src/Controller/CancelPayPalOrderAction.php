<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

final class CancelPayPalOrderAction
{
    /** @var PaymentProviderInterface */
    private $paymentProvider;

    /** @var ObjectManager */
    private $manager;

    /** @var FlashBag */
    private $flashBag;

    public function __construct(
        PaymentProviderInterface $paymentProvider,
        ObjectManager $manager,
        FlashBag $flashBag
    ) {
        $this->paymentProvider = $paymentProvider;
        $this->manager = $manager;
        $this->flashBag = $flashBag;
    }

    public function __invoke(Request $request): Response
    {
        $content = (array) json_decode((string) $request->getContent(false), true);

        $payment = $this->paymentProvider->getByPayPalOrderId((string) $content['payPalOrderId']);

        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        $this->manager->remove($order);

        $this->flashBag->add('success', 'sylius.pay_pal.order_cancel');
        $this->manager->flush();

        return new Response();
    }
}
