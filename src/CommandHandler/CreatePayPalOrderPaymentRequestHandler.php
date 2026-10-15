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
use Sylius\PayPalPlugin\Checker\PaymentSourceEnabledCheckerInterface;
use Sylius\PayPalPlugin\Command\CreatePayPalOrderPaymentRequest;
use Sylius\PayPalPlugin\Creator\PayPalOrderCreatorInterface;
use Sylius\PayPalPlugin\Exception\InvalidPayerDataException;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CreatePayPalOrderPaymentRequestHandler
{
    use FailPaymentRequestTrait;

    public const PAYLOAD_PAYMENT_SOURCE = 'payment_source';

    public const RESPONSE_PAYPAL_ORDER_ID = 'paypal_order_id';

    public const RESPONSE_PAYER_ACTION_URL = 'payer_action_url';

    public const PAYER_CANCELLED_QUERY_PARAMETER = 'payer_cancelled';

    public function __construct(
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
        private readonly PayPalOrderCreatorInterface $payPalOrderCreator,
        private readonly PayPalPaymentSourceProviderInterface $paymentSourceProvider,
        private readonly PaymentSourceEnabledCheckerInterface $paymentSourceEnabledChecker,
        private readonly UrlProviderInterface $returnUrlProvider,
        StateMachineInterface $stateMachine,
    ) {
        $this->stateMachine = $stateMachine;
    }

    public function __invoke(CreatePayPalOrderPaymentRequest $createPayPalOrderPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($createPayPalOrderPaymentRequest);

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

        if (!$this->paymentSourceEnabledChecker->isEnabled($paymentSource, $paymentRequest->getMethod())) {
            $this->failWithReason($paymentRequest, 'The requested payment source is not enabled for this payment method.');

            return;
        }

        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();
        $hash = (string) $paymentRequest->getId();

        try {
            $payUrl = $this->returnUrlProvider->getUrl($paymentRequest, UrlGeneratorInterface::ABSOLUTE_URL);
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
        } catch (PayPalApiException) {
            $this->failWithReason($paymentRequest, 'PayPal did not create the order.');

            return;
        }

        if (null === $details) {
            $this->failWithReason($paymentRequest, 'PayPal did not create the order.');

            return;
        }

        $paymentRequest->setResponseData(array_filter([
            self::RESPONSE_PAYPAL_ORDER_ID => $details->payPalOrderId(),
            self::RESPONSE_PAYER_ACTION_URL => $details->payerActionUrl(),
        ]));

        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);
    }
}
