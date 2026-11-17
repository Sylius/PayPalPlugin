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

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;

trait FailThreeDSecureAuthenticationTrait
{
    use FailPaymentRequestTrait;

    public const THREE_D_SECURE = 'three_d_secure';

    public const THREE_D_SECURE_RETRY = 'retry';

    public const THREE_D_SECURE_DECLINED = 'declined';

    private function endWithFailedAuthentication(PaymentRequestInterface $paymentRequest, PaymentInterface $payment, bool $retryable): void
    {
        if ($retryable) {
            $paymentRequest->setResponseData([
                self::RESPONSE_REASON => 'The 3D Secure authentication of the card did not finish.',
                self::THREE_D_SECURE => self::THREE_D_SECURE_RETRY,
            ]);
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL);

            return;
        }

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL);
        $paymentRequest->setResponseData([
            self::RESPONSE_REASON => 'The 3D Secure authentication refused the card.',
            self::THREE_D_SECURE => self::THREE_D_SECURE_DECLINED,
        ]);
        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL);
    }
}
