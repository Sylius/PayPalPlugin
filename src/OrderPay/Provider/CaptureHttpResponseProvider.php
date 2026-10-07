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

namespace Sylius\PayPalPlugin\OrderPay\Provider;

use Sylius\Bundle\PaymentBundle\Provider\HttpResponseProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\CommandHandler\CaptureEndPaymentRequestHandler;
use Sylius\PayPalPlugin\Provider\PayPalPaymentPageContextProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class CaptureHttpResponseProvider implements HttpResponseProviderInterface
{
    public function __construct(
        private Environment $twig,
        private PayPalPaymentPageContextProviderInterface $paymentPageContextProvider,
        private UrlGeneratorInterface $router,
        private string $webUrl,
    ) {
    }

    public function supports(RequestConfiguration|Request $request, PaymentRequestInterface $paymentRequest): bool
    {
        return match ($paymentRequest->getState()) {
            PaymentRequestInterface::STATE_NEW, PaymentRequestInterface::STATE_CANCELLED => true,
            PaymentRequestInterface::STATE_FAILED => $this->isThreeDSecure($paymentRequest, CaptureEndPaymentRequestHandler::THREE_D_SECURE_DECLINED),
            default => false,
        };
    }

    public function getResponse(RequestConfiguration|Request $request, PaymentRequestInterface $paymentRequest): Response
    {
        if ($request instanceof RequestConfiguration) {
            $request = $request->getRequest();
        }

        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        return match ($paymentRequest->getState()) {
            PaymentRequestInterface::STATE_CANCELLED => $this->sendBackToPay($request, $paymentRequest, $order),
            PaymentRequestInterface::STATE_FAILED => $this->sendToOrder($request, $order),
            default => $this->renderPaymentPage($request, $paymentRequest, $payment),
        };
    }

    private function sendBackToPay(Request $request, PaymentRequestInterface $paymentRequest, OrderInterface $order): Response
    {
        if ($this->isThreeDSecure($paymentRequest, CaptureEndPaymentRequestHandler::THREE_D_SECURE_RETRY)) {
            $this->addErrorFlash($request, 'sylius_paypal.three_d_secure_retry');
        }

        return new RedirectResponse($this->router->generate('sylius_shop_order_pay', ['tokenValue' => $order->getTokenValue()]));
    }

    private function sendToOrder(Request $request, OrderInterface $order): Response
    {
        $this->addErrorFlash($request, 'sylius_paypal.three_d_secure_declined');

        return new RedirectResponse($this->router->generate('sylius_shop_order_show', ['tokenValue' => $order->getTokenValue()]));
    }

    private function renderPaymentPage(Request $request, PaymentRequestInterface $paymentRequest, PaymentInterface $payment): Response
    {
        $context = $this->paymentPageContextProvider->provide($payment, $request->getLocale());
        $context['createPayPalOrderUrl'] = $this->router->generate(
            'sylius_paypal_shop_create_paypal_order_for_payment_request',
            ['hash' => $paymentRequest->getId()],
        );

        $response = new Response($this->twig->render('@SyliusPayPalPlugin/pay_with_paypal.html.twig', $context));
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Permissions-Policy', sprintf(
            'publickey-credentials-get=(self "%1$s"), publickey-credentials-create=(self "%1$s")',
            $this->webUrl,
        ));

        return $response;
    }

    private function isThreeDSecure(PaymentRequestInterface $paymentRequest, string $outcome): bool
    {
        return $outcome === ($paymentRequest->getResponseData()[CaptureEndPaymentRequestHandler::THREE_D_SECURE] ?? null);
    }

    private function addErrorFlash(Request $request, string $message): void
    {
        /** @var FlashBagInterface $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');
        $flashBag->add('error', $message);
    }
}
