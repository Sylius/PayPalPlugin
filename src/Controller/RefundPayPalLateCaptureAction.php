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
use Sylius\PayPalPlugin\Exception\PayPalOrderRefundException;
use Sylius\PayPalPlugin\Processor\PaymentRefundProcessorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class RefundPayPalLateCaptureAction
{
    /** @param PaymentRepositoryInterface<PaymentInterface> $paymentRepository */
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentRefundProcessorInterface $lateCaptureRefundProcessor,
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
        if (null === $payment) {
            throw new NotFoundHttpException(sprintf('Payment #%s of order #%s not found.', $paymentId, (string) $orderId));
        }

        /** @var FlashBagInterface $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');

        try {
            $this->lateCaptureRefundProcessor->refund($payment);
            $flashBag->add('success', 'sylius_paypal.late_capture_refunded');
        } catch (PayPalOrderRefundException) {
            $flashBag->add('error', 'sylius_paypal.late_capture_not_refunded');
        }

        return new RedirectResponse($this->router->generate('sylius_admin_order_show', ['id' => $orderId]));
    }
}
