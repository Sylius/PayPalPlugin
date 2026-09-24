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

namespace Sylius\PayPalPlugin\Controller\Webhook;

use Doctrine\Persistence\ObjectManager;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\WebhookSignatureVerifierInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalRefundDataProviderInterface;
use Sylius\PayPalPlugin\Provider\WebhookIdProviderInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Sylius\PayPalPlugin\Verifier\PayPalWebhookRequestVerifier;
use Sylius\PayPalPlugin\Verifier\PayPalWebhookRequestVerifierInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final readonly class RefundOrderAction
{
    public function __construct(
        private StateMachineInterface $stateMachineFactory,
        private ?PaymentProviderInterface $paymentProvider,
        private ObjectManager $paymentManager,
        private PayPalRefundDataProviderInterface $payPalRefundDataProvider,
        private PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private WebhookSignatureVerifierInterface $webhookSignatureVerifier,
        private WebhookIdProviderInterface $webhookIdProvider,
        private ?PaypalPaymentQueryInterface $paypalPaymentQuery = null,
        private ?PayPalWebhookRequestVerifierInterface $requestVerifier = null,
    ) {
        if (null !== $this->paymentProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.7',
                sprintf(
                    'Passing an instance of "%s" as the second argument is deprecated and will be prohibited in 3.0',
                    PaymentProviderInterface::class,
                ),
            );
        }
        if (null === $this->paypalPaymentQuery) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.7',
                sprintf(
                    'Not passing an instance of "%s" is deprecated and will be prohibited in 3.0',
                    PaypalPaymentQueryInterface::class,
                ),
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->requestVerifier()->verify($request)) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $refundData = $this->payPalRefundDataProvider->provide($this->getPayPalPaymentUrl($request));

        try {
            if (null !== $this->paypalPaymentQuery) {
                $payment = $this->paypalPaymentQuery->getForRefundingByOrderId((string) $refundData['id']);
            } else {
                $payment = $this->paymentProvider->getByPayPalOrderId((string) $refundData['id']);
            }
        } catch (PaymentNotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        }

        if ($this->stateMachineFactory->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)) {
            $this->stateMachineFactory->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND);
        }

        $this->paymentManager->flush();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    private function requestVerifier(): PayPalWebhookRequestVerifierInterface
    {
        return $this->requestVerifier ?? new PayPalWebhookRequestVerifier(
            $this->payPalPaymentMethodProvider,
            $this->webhookIdProvider,
            $this->authorizeClientApi,
            $this->webhookSignatureVerifier,
        );
    }

    private function getPayPalPaymentUrl(Request $request): string
    {
        /**
         * @var string $content
         */
        $content = $request->getContent();

        $content = (array) json_decode($content, true);
        Assert::keyExists($content, 'resource');
        $resource = (array) $content['resource'];
        Assert::keyExists($resource, 'links');

        /** @var string[] $link */
        foreach ($resource['links'] as $link) {
            if ($link['rel'] === 'up') {
                return $link['href'];
            }
        }

        throw new PayPalWrongDataException();
    }
}
