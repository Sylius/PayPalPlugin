<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Contracts\Translation\TranslatorInterface;

class CancelPayPalOrderAction
{
    /** @var PaymentProviderInterface */
    private $paymentProvider;

    /** @var ObjectManager */
    private $manager;

    /** @var FlashBag */
    private $flashBag;

    /** @var TranslatorInterface */
    private $translator;

    public function __construct(
        PaymentProviderInterface $paymentProvider,
        ObjectManager $manager,
        FlashBag $flashBag,
        TranslatorInterface $translator
    ) {
        $this->paymentProvider = $paymentProvider;
        $this->manager = $manager;
        $this->flashBag = $flashBag;
        $this->translator = $translator;
    }

    public function __invoke(Request $request): Response
    {
        $content = (array) json_decode((string) $request->getContent(false), true);

        $payment = $this->paymentProvider->getByPayPalOrderId((string) $content['payPalOrderId']);

        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        $order->clearItems();

        $this->flashBag->add('success', $this->translator->trans('sylius.pay_pal.order_cancel'));
        $this->manager->flush();

        return new JsonResponse(['url' => '/']);
    }
}
