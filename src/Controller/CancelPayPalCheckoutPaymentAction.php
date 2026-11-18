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

use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/** @deprecated since Sylius/PayPalPlugin 2.2 and will be removed in Sylius/PayPalPlugin 3.0. */
final readonly class CancelPayPalCheckoutPaymentAction
{
    public function __construct(
        private ?PaymentProviderInterface $paymentProvider,
        private PaymentStateManagerInterface $paymentStateManager,
        private ?PaypalPaymentQueryInterface $paypalPaymentQuery = null,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $payload = $request->getPayload();
        $paypalOrderId = $payload->getString('payPalOrderId');

        if (null !== $this->paypalPaymentQuery) {
            $payment = $this->paypalPaymentQuery->getForCancellationByOrderId($paypalOrderId);
        } else {
            $payment = $this->paymentProvider->getByPayPalOrderId($paypalOrderId);
        }

        /** @var FlashBagInterface $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');
        $flashBag->add('success', 'sylius_paypal.payment_cancelled');

        $this->paymentStateManager->cancel($payment);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
