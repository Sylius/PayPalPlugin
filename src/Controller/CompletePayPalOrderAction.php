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

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class CompletePayPalOrderAction
{
    public function __construct(
        private PaymentStateManagerInterface $paymentStateManager,
        private UrlGeneratorInterface $router,
        private OrderProviderInterface $orderProvider,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        trigger_deprecation(
            'sylius/paypal-plugin',
            '2.1',
            'The "sylius_paypal_shop_complete_paypal_order" route is deprecated and will be removed in 3.0.' .
            ' Use "sylius_paypal_shop_process_paypal_order" or "..._complete_paypal_order_from_payment_page" (the v6 Web SDK placements) instead.',
        );

        $token = (string) $request->attributes->get('token');
        $order = $this->orderProvider->provideOrderByToken($token);
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);

        $this->paymentStateManager->complete($payment);

        return new JsonResponse([
            'orderID' => $payment->getDetails()['paypal_order_id'],
            'status' => $payment->getState(),
            'return_url' => $this->router->generate('sylius_shop_order_thank_you', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }
}
