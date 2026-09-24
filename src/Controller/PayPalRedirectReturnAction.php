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

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Checker\PayerActionCheckerInterface;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PayPalRedirectReturnAction
{
    public function __construct(
        private OrderProviderInterface $orderProvider,
        private PaymentSettlementProcessorInterface $paymentSettlementProcessor,
        private PayerActionCheckerInterface $payerActionChecker,
        private UrlGeneratorInterface $router,
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $order = $this->orderProvider->provideOrderByToken((string) $request->attributes->get('token'));

        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);
        if (null !== $payment) {
            $nonce = (string) $request->attributes->get('nonce');

            if (!$this->payerActionChecker->matchesPayerActionReturnNonce($payment, $nonce)) {
                throw new NotFoundHttpException(sprintf(
                    'Payment "%s" was not started by the payer action that came back.',
                    (string) $payment->getId(),
                ));
            }

            $this->paymentSettlementProcessor->settle($payment);
        }

        return new RedirectResponse($this->destination($order, $payment));
    }

    private function destination(OrderInterface $order, ?PaymentInterface $payment): string
    {
        if (null !== $payment && PaymentInterface::STATE_COMPLETED === $payment->getState()) {
            return $this->router->generate('sylius_shop_order_thank_you');
        }

        if (null !== $payment && PaymentInterface::STATE_PROCESSING === $payment->getState()) {
            $this->addFlash('info', 'sylius_paypal.payment_pending');

            return $this->router->generate('sylius_shop_order_thank_you');
        }

        if (null !== $payment) {
            $this->addFlash('error', 'sylius_paypal.something_went_wrong');
        }

        return $this->router->generate('sylius_shop_order_show', ['tokenValue' => $order->getTokenValue()]);
    }

    private function addFlash(string $type, string $message): void
    {
        FlashBagProvider::getFlashBag($this->requestStack)->add($type, $message);
    }
}
