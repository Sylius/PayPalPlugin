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
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\CommandHandler\CaptureAuthorizationPaymentRequestHandler;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Dispatcher\CaptureAuthorizationPaymentRequestDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class CaptureAuthorizationAction
{
    /** @param PaymentRepositoryInterface<PaymentInterface> $paymentRepository */
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private CaptureAuthorizationPaymentRequestDispatcherInterface $captureAuthorizationPaymentRequestDispatcher,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $paymentId = (string) $request->attributes->get('id');
        $orderId = $request->attributes->get('orderId');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($paymentId, (string) $request->request->get('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $payment = $this->paymentRepository->findOneByOrderId($paymentId, $orderId);
        if (
            null === $payment ||
            PaymentInterface::STATE_AUTHORIZED !== $payment->getState() ||
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $payment->getMethod()?->getGatewayConfig()?->getFactoryName()
        ) {
            throw new NotFoundHttpException(sprintf('Authorized PayPal payment #%s of order #%s not found.', $paymentId, (string) $orderId));
        }

        $paymentRequest = $this->captureAuthorizationPaymentRequestDispatcher->dispatch($payment);

        /** @var FlashBagInterface $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');

        match ($paymentRequest->getState()) {
            PaymentRequestInterface::STATE_COMPLETED => $flashBag->add('success', 'sylius.payment.completed'),
            PaymentRequestInterface::STATE_FAILED => $flashBag->add('error', [
                'message' => 'sylius_paypal.payment_not_captured',
                'parameters' => ['%reason%' => (string) ($paymentRequest->getResponseData()[CaptureAuthorizationPaymentRequestHandler::RESPONSE_REASON] ?? '')],
            ]),
            default => $flashBag->add('info', 'sylius_paypal.payment_capture_pending'),
        };

        return new RedirectResponse($this->router->generate('sylius_admin_order_show', ['id' => $orderId]));
    }
}
