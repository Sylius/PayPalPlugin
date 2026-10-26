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

namespace Sylius\PayPalPlugin\CommandHandler;

use Psr\Container\ContainerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\PayPalPlugin\Api\WebhookResourceApiInterface;
use Sylius\PayPalPlugin\Command\NotifyPaymentRequest;
use Sylius\PayPalPlugin\Exception\PermanentWebhookFailureInterface;
use Sylius\PayPalPlugin\Model\PayPalWebhookEventNotification;
use Sylius\PayPalPlugin\Model\PayPalWebhookEventStatus;
use Sylius\PayPalPlugin\Processor\Webhook\PayloadResourceWebhookEventProcessorInterface;
use Sylius\PayPalPlugin\Processor\Webhook\WebhookEventProcessorInterface;
use Sylius\PayPalPlugin\Repository\PayPalWebhookEventRepositoryInterface;

final class NotifyPaymentRequestHandler
{
    use FailPaymentRequestTrait;

    public const RESPONSE_WEBHOOK_EVENT_STATUS = 'webhook_event_status';

    public const WEBHOOK_EVENT_DUPLICATE = 'duplicate';

    public function __construct(
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
        private readonly PayPalWebhookEventRepositoryInterface $webhookEventRepository,
        private readonly ContainerInterface $webhookEventProcessors,
        private readonly WebhookResourceApiInterface $webhookResourceApi,
        StateMachineInterface $stateMachine,
    ) {
        $this->stateMachine = $stateMachine;
    }

    public function __invoke(NotifyPaymentRequest $notifyPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($notifyPaymentRequest);

        $notification = $this->notification($paymentRequest);
        if (null === $notification) {
            $this->failWithReason($paymentRequest, 'The notification carries no PayPal webhook event.');

            return;
        }

        $eventId = $notification->id();
        $eventType = $notification->eventType();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $paymentRequest->getMethod();
        $paymentMethodCode = (string) $paymentMethod->getCode();

        if (!$this->webhookEventRepository->claim($eventId, $paymentMethodCode, $eventType)) {
            $this->complete($paymentRequest, self::WEBHOOK_EVENT_DUPLICATE);

            return;
        }

        if (!$this->webhookEventProcessors->has($eventType)) {
            $this->webhookEventRepository->markAs($eventId, $paymentMethodCode, PayPalWebhookEventStatus::Ignored);
            $this->complete($paymentRequest, PayPalWebhookEventStatus::Ignored->value);

            return;
        }

        /** @var WebhookEventProcessorInterface $webhookEventProcessor */
        $webhookEventProcessor = $this->webhookEventProcessors->get($eventType);
        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();

        try {
            $resource = $webhookEventProcessor instanceof PayloadResourceWebhookEventProcessorInterface
                ? $notification->resource()
                : $this->webhookResourceApi->get($paymentMethod, $notification->resourceType(), $notification->resourceId());
            $webhookEventProcessor->process($eventType, $resource, $payment);
        } catch (PermanentWebhookFailureInterface $exception) {
            $this->webhookEventRepository->markAs($eventId, $paymentMethodCode, PayPalWebhookEventStatus::Failed);
            $this->failWithReason($paymentRequest, $exception->getMessage());

            return;
        }

        $this->webhookEventRepository->markAs($eventId, $paymentMethodCode, PayPalWebhookEventStatus::Processed);
        $this->complete($paymentRequest, PayPalWebhookEventStatus::Processed->value);
    }

    private function notification(PaymentRequestInterface $paymentRequest): ?PayPalWebhookEventNotification
    {
        $payload = $paymentRequest->getPayload();
        $content = is_array($payload) ? ($payload['http_request']['content'] ?? null) : null;

        return is_string($content) ? PayPalWebhookEventNotification::fromJson($content) : null;
    }

    private function complete(PaymentRequestInterface $paymentRequest, string $webhookEventStatus): void
    {
        $paymentRequest->setResponseData([self::RESPONSE_WEBHOOK_EVENT_STATUS => $webhookEventStatus]);

        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
    }
}
