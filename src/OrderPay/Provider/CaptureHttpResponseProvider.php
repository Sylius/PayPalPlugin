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
use Sylius\PayPalPlugin\Provider\PayPalPaymentPageContextProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        return in_array(
            $paymentRequest->getState(),
            [PaymentRequestInterface::STATE_NEW, PaymentRequestInterface::STATE_CANCELLED],
            true,
        );
    }

    public function getResponse(RequestConfiguration|Request $request, PaymentRequestInterface $paymentRequest): Response
    {
        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        if (PaymentRequestInterface::STATE_CANCELLED === $paymentRequest->getState()) {
            return new RedirectResponse($this->router->generate('sylius_shop_order_pay', ['tokenValue' => $order->getTokenValue()]));
        }

        if ($request instanceof RequestConfiguration) {
            $request = $request->getRequest();
        }

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
}
