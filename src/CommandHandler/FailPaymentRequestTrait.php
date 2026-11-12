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
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;

trait FailPaymentRequestTrait
{
    public const RESPONSE_REASON = 'reason';

    private readonly StateMachineInterface $stateMachine;

    private function failWithReason(PaymentRequestInterface $paymentRequest, string $reason): void
    {
        $paymentRequest->setResponseData([self::RESPONSE_REASON => $reason]);

        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL);
    }
}
