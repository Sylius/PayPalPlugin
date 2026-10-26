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

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PayPalWebhookEventIgnoredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookSignatureNotVerifiedException;
use Sylius\PayPalPlugin\Processor\Webhook\VaultingCapabilityWebhookProcessorInterface;
use Sylius\PayPalPlugin\Verifier\WebhookRequestVerifierInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class PaymentMethodNotifyAction
{
    /** @var \Closure(Request, string): Response */
    private \Closure $decoratedAction;

    /**
     * @param callable(Request, string): Response $decoratedAction
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     */
    public function __construct(
        callable $decoratedAction,
        private LoggerInterface $logger,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private WebhookRequestVerifierInterface $webhookRequestVerifier,
        private VaultingCapabilityWebhookProcessorInterface $vaultingCapabilityWebhookProcessor,
    ) {
        $this->decoratedAction = $decoratedAction(...);
    }

    public function __invoke(Request $request, string $code): Response
    {
        try {
            return $this->processVaultingCapability($request, $code) ?? ($this->decoratedAction)($request, $code);
        } catch (PayPalWebhookSignatureNotVerifiedException $exception) {
            $this->logger->warning($exception->getMessage());

            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        } catch (PayPalWebhookEventIgnoredException $exception) {
            $this->logger->info($exception->getMessage());

            return new Response('', Response::HTTP_NO_CONTENT);
        }
    }

    private function processVaultingCapability(Request $request, string $code): ?Response
    {
        $event = json_decode($request->getContent(), true);
        if (
            !is_array($event) ||
            VaultingCapabilityWebhookProcessorInterface::EVENT_TYPE !== ($event['event_type'] ?? null) ||
            !is_array($event['resource'] ?? null)
        ) {
            return null;
        }

        $paymentMethod = $this->paymentMethodRepository->findOneBy(['code' => $code]);
        if (
            !$paymentMethod instanceof PaymentMethodInterface ||
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $paymentMethod->getGatewayConfig()?->getFactoryName()
        ) {
            return null;
        }

        if (!$this->webhookRequestVerifier->verify($request, $paymentMethod)) {
            throw new PayPalWebhookSignatureNotVerifiedException($code);
        }

        $this->vaultingCapabilityWebhookProcessor->process($event['resource'], $paymentMethod);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
