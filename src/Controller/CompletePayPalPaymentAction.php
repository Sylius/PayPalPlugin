<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use FOS\RestBundle\View\View;
use Payum\Core\Payum;
use SM\Factory\FactoryInterface;
use Sylius\Bundle\PayumBundle\Factory\ResolveNextRouteFactoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\StateMachine\StateMachineInterface;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompletePayPalPaymentAction
{
    /** @var Payum */
    private $payum;

    /** @var PaymentRepositoryInterface */
    private $paymentRepository;

    /** @var ResolveNextRouteFactoryInterface */
    private $resolveNextRouteRequestFactory;

    /** @var PaymentStateManagerInterface */
    private $paymentStateManager;

    /** @var UrlGeneratorInterface */
    private $router;

    public function __construct(
        Payum $payum,
        PaymentRepositoryInterface $paymentRepository,
        ResolveNextRouteFactoryInterface $resolveNextRouteRequestFactory,
        PaymentStateManagerInterface $paymentStateManager,
        UrlGeneratorInterface $router
    ) {
        $this->payum = $payum;
        $this->paymentRepository = $paymentRepository;
        $this->resolveNextRouteRequestFactory = $resolveNextRouteRequestFactory;
        $this->paymentStateManager = $paymentStateManager;
        $this->router = $router;
    }

    public function __invoke(Request $request): Response
    {
        /** @var PaymentInterface $payment */
        $payment = $this->paymentRepository->find($request->attributes->get('id'));
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $status = $request->query->get('status');
        $this->paymentStateManager->changeState($payment, $status);

        $resolveNextRoute = $this->resolveNextRouteRequestFactory->createNewWithModel($payment);
        $this->payum->getGateway($paymentMethod->getGatewayConfig()->getGatewayName())->execute($resolveNextRoute);

        /** @var FlashBagInterface $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');
        $flashBag->clear();
        $flashBag->add('success', 'sylius.payment.completed');

        return new RedirectResponse(
            $this->router->generate($resolveNextRoute->getRouteName(), $resolveNextRoute->getRouteParameters())
        );
    }
}
