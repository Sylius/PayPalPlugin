<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Enabler\PaymentMethodEnablerInterface;
use Sylius\PayPalPlugin\Exception\PaymentMethodCouldNotBeEnabledException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class EnableSellerAction
{
    private PaymentMethodRepositoryInterface $paymentMethodRepository;

    private PaymentMethodEnablerInterface $paymentMethodEnabler;

    public function __construct(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        PaymentMethodEnablerInterface $paymentMethodEnabler
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentMethodEnabler = $paymentMethodEnabler;
    }

    public function __invoke(Request $request): Response
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $this->paymentMethodRepository->find($request->attributes->getInt('id'));
        /** @var FlashBagInterface $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');

        try {
            $this->paymentMethodEnabler->enable($paymentMethod);
        } catch (PaymentMethodCouldNotBeEnabledException $exception) {
            $flashBag->add('error', 'sylius.pay_pal.payment_not_enabled');

            return new RedirectResponse((string) $request->headers->get('referer'));
        } catch (PayPalWebhookUrlNotValidException $exception) {
            $flashBag->add('error', 'sylius.pay_pal.webhook_url_not_valid');

            return new RedirectResponse((string) $request->headers->get('referer'));
        }

        $flashBag->add('success', 'sylius.pay_pal.payment_enabled');

        return new RedirectResponse((string) $request->headers->get('referer'));
    }
}
