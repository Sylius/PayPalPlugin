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

namespace Sylius\PayPalPlugin\EventListener\Workflow;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Dispatcher\VoidAuthorizationPaymentRequestDispatcherInterface;
use Sylius\PayPalPlugin\Model\PayPalAuthorization;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Symfony\Component\Workflow\Event\CompletedEvent;

final readonly class VoidCancelledAuthorizationListener
{
    public function __construct(
        private VoidAuthorizationPaymentRequestDispatcherInterface $voidAuthorizationPaymentRequestDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $payment = $event->getSubject();
        if (
            !$payment instanceof PaymentInterface ||
            !in_array(PaymentInterface::STATE_AUTHORIZED, $event->getTransition()?->getFroms() ?? [], true)
        ) {
            return;
        }

        $this->void($payment);
    }

    public function void(PaymentInterface $payment): void
    {
        $details = PayPalPaymentDetails::fromPayment($payment);
        if (
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $payment->getMethod()?->getGatewayConfig()?->getFactoryName() ||
            null === $details->authorizationId() ||
            PayPalAuthorization::isReleasedStatus($details->authorizationStatus())
        ) {
            return;
        }

        try {
            $this->voidAuthorizationPaymentRequestDispatcher->dispatch($payment);
        } catch (\Throwable $exception) {
            $this->logger->error(sprintf(
                'Voiding the PayPal authorization %s of cancelled payment #%s failed: %s',
                (string) $details->authorizationId(),
                (string) $payment->getId(),
                $exception->getMessage(),
            ), ['exception' => $exception]);
        }
    }
}
