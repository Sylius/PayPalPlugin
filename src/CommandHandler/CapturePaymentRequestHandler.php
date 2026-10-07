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

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\CoreBundle\OrderPay\Provider\UrlProviderInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\PayPalPlugin\Command\CapturePaymentRequest;
use Sylius\PayPalPlugin\Creator\PayPalOrderCreatorInterface;
use Sylius\PayPalPlugin\Exception\InvalidPayerDataException;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CapturePaymentRequestHandler
{
    use FailPaymentRequestTrait;

    public const PAYLOAD_PAYMENT_SOURCE = 'payment_source';

    public const PAYER_CANCELLED_QUERY_PARAMETER = 'payer_cancelled';

    public function __construct(
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
        private readonly PayPalOrderCreatorInterface $payPalOrderCreator,
        private readonly PayPalPaymentSourceProviderInterface $paymentSourceProvider,
        private readonly UrlProviderInterface $paymentRequestPayUrlProvider,
        StateMachineInterface $stateMachine,
    ) {
        $this->stateMachine = $stateMachine;
    }

    public function __invoke(CapturePaymentRequest $capturePaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($capturePaymentRequest);

        if (PaymentRequestInterface::STATE_NEW !== $paymentRequest->getState()) {
            return;
        }

        $payload = $paymentRequest->getPayload();
        if (!is_array($payload) || !array_key_exists(self::PAYLOAD_PAYMENT_SOURCE, $payload)) {
            return;
        }

        $paymentSource = $payload[self::PAYLOAD_PAYMENT_SOURCE];
        if (!is_string($paymentSource) || !$this->paymentSourceProvider->supports($paymentSource)) {
            $this->failWithReason($paymentRequest, 'PayPal does not support the requested payment source.');

            return;
        }

        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();
        $hash = (string) $paymentRequest->getId();

        try {
            $payUrl = $this->paymentRequestPayUrlProvider->getUrl($paymentRequest, UrlGeneratorInterface::ABSOLUTE_URL);
            $details = $this->payPalOrderCreator->create(
                $payment,
                $paymentSource,
                $hash,
                $hash,
                $payUrl,
                sprintf('%s?%s=1', $payUrl, self::PAYER_CANCELLED_QUERY_PARAMETER),
            );
        } catch (InvalidPayerDataException $exception) {
            $this->failWithReason($paymentRequest, $exception->getMessage());

            return;
        }

        if (null === $details) {
            $this->failWithReason($paymentRequest, 'PayPal did not create the order.');

            return;
        }

        $paymentRequest->setResponseData(array_filter([
            'paypal_order_id' => $details->payPalOrderId(),
            'payer_action_url' => $details->payerActionUrl(),
        ]));

        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);
    }
}
