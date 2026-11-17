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

namespace Sylius\PayPalPlugin\CommandProvider;

use Sylius\Bundle\CoreBundle\OrderPay\Provider\UrlProviderInterface;
use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\Command\AuthorizePayPalOrderPaymentRequest;
use Sylius\PayPalPlugin\Command\CreatePayPalOrderPaymentRequest;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AuthorizePaymentRequestCommandProvider implements PaymentRequestCommandProviderInterface
{
    public function __construct(private readonly UrlProviderInterface $returnUrlProvider)
    {
    }

    public function supports(PaymentRequestInterface $paymentRequest): bool
    {
        return PaymentRequestInterface::ACTION_AUTHORIZE === $paymentRequest->getAction();
    }

    public function provide(PaymentRequestInterface $paymentRequest): object
    {
        if (PaymentRequestInterface::STATE_PROCESSING === $paymentRequest->getState()) {
            return new AuthorizePayPalOrderPaymentRequest($paymentRequest->getId());
        }

        return new CreatePayPalOrderPaymentRequest(
            $paymentRequest->getId(),
            $this->returnUrlProvider->getUrl($paymentRequest, UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }
}
