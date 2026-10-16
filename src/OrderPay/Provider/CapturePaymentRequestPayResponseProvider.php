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

use Sylius\Bundle\CoreBundle\OrderPay\Provider\PayResponseProviderInterface;
use Sylius\Bundle\CoreBundle\OrderPay\Provider\UrlProviderInterface;
use Sylius\Bundle\CoreBundle\OrderPay\Resolver\PaymentToPayResolverInterface;
use Sylius\Bundle\PaymentBundle\Provider\DefaultActionProviderInterface;
use Sylius\Bundle\PaymentBundle\Provider\DefaultPayloadProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Payment\Factory\PaymentRequestFactoryInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

/**
 * @internal Works around Sylius' PaymentRequestRepository::findOneByActionPaymentAndMethod() failing with several payment requests per payment; remove once the fix is released in all supported Sylius versions.
 */
final readonly class CapturePaymentRequestPayResponseProvider implements PayResponseProviderInterface
{
    private const OPEN_STATES = [PaymentRequestInterface::STATE_NEW, PaymentRequestInterface::STATE_PROCESSING];

    /**
     * @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository
     * @param PaymentRequestFactoryInterface<PaymentRequestInterface> $paymentRequestFactory
     */
    public function __construct(
        private PaymentToPayResolverInterface $paymentToPayResolver,
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private PaymentRequestFactoryInterface $paymentRequestFactory,
        private DefaultActionProviderInterface $defaultActionProvider,
        private DefaultPayloadProviderInterface $defaultPayloadProvider,
        private UrlProviderInterface $paymentRequestPayUrlProvider,
    ) {
    }

    public function supports(RequestConfiguration|Request $request, OrderInterface $order): bool
    {
        /** @var GatewayConfigInterface|null $gatewayConfig */
        $gatewayConfig = $this->paymentToPayResolver->getPayment($order)?->getMethod()?->getGatewayConfig();

        return SyliusPayPalExtension::PAYPAL_FACTORY_NAME === $gatewayConfig?->getFactoryName();
    }

    public function getResponse(RequestConfiguration|Request $request, OrderInterface $order): Response
    {
        $payment = $this->paymentToPayResolver->getPayment($order);
        Assert::notNull($payment);
        $paymentMethod = $payment->getMethod();
        Assert::notNull($paymentMethod);

        $paymentRequest = $this->paymentRequestFactory->create($payment, $paymentMethod);
        $paymentRequest->setAction($this->defaultActionProvider->getAction($paymentRequest));

        $paymentRequest = $this->openAttempt($paymentRequest) ?? $this->newAttempt($paymentRequest);

        return new RedirectResponse($this->paymentRequestPayUrlProvider->getUrl($paymentRequest));
    }

    private function openAttempt(PaymentRequestInterface $newPaymentRequest): ?PaymentRequestInterface
    {
        $openAttempts = array_filter(
            $this->paymentRequestRepository->findByPaymentIdAndStates($newPaymentRequest->getPayment()->getId(), self::OPEN_STATES),
            static fn (PaymentRequestInterface $paymentRequest): bool => $newPaymentRequest->getAction() === $paymentRequest->getAction() &&
                $newPaymentRequest->getMethod() === $paymentRequest->getMethod(),
        );
        usort($openAttempts, static fn (PaymentRequestInterface $first, PaymentRequestInterface $second): int => $second->getCreatedAt() <=> $first->getCreatedAt());

        return $openAttempts[0] ?? null;
    }

    private function newAttempt(PaymentRequestInterface $paymentRequest): PaymentRequestInterface
    {
        $paymentRequest->setPayload($this->defaultPayloadProvider->getPayload($paymentRequest));

        $this->paymentRequestRepository->add($paymentRequest);

        return $paymentRequest;
    }
}
