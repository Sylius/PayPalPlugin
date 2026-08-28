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

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Enabler\PaymentMethodEnablerInterface;
use Sylius\PayPalPlugin\Exception\PaymentMethodCouldNotBeEnabledException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final readonly class EnableSellerAction
{
    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository */
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private PaymentMethodEnablerInterface $paymentMethodEnabler,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $paymentMethod = $this->paymentMethodRepository->find($request->attributes->getInt('id'));
        /** @var FlashBagInterface $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');

        try {
            $this->paymentMethodEnabler->enable($paymentMethod);
        } catch (PaymentMethodCouldNotBeEnabledException) {
            $flashBag->add('error', 'sylius_paypal.payment_not_enabled');

            return new RedirectResponse((string) $request->headers->get('referer'));
        } catch (PayPalWebhookUrlNotValidException) {
            $flashBag->add('error', 'sylius_paypal.webhook_url_not_valid');

            return new RedirectResponse((string) $request->headers->get('referer'));
        }

        $flashBag->add('success', 'sylius_paypal.payment_enabled');

        return new RedirectResponse((string) $request->headers->get('referer'));
    }
}
