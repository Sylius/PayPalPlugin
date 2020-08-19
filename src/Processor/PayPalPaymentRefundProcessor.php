<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalOrderRefundException;

final class PayPalPaymentRefundProcessor implements PaymentRefundProcessorInterface
{
    /** @var AuthorizeClientApiInterface */
    private $authorizeClientApi;

    /** @var RefundPaymentApiInterface */
    private $refundOrderApi;

    public function __construct(
        AuthorizeClientApiInterface $authorizeClientApi,
        RefundPaymentApiInterface $refundOrderApi
    ) {
        $this->authorizeClientApi = $authorizeClientApi;
        $this->refundOrderApi = $refundOrderApi;
    }

    public function refund(PaymentInterface $payment): void
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = $gatewayConfig->getConfig();

        if ($gatewayConfig->getFactoryName() !== 'sylius.pay_pal') {
            return;
        }

        $details = $payment->getDetails();
        if (!isset($details['paypal_payment_id'])) {
            return;
        }

        $token = $this->authorizeClientApi->authorize((string) $config['client_id'], (string) $config['client_secret']);
        $response = $this->refundOrderApi->refund($token, (string) $details['paypal_payment_id']);

        if ($response['status'] === 'COMPLETED') {
            return;
        }

        throw new PayPalOrderRefundException();
    }
}
