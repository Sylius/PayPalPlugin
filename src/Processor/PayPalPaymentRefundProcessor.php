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

use GuzzleHttp\Exception\ClientException;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalOrderRefundException;
use Sylius\PayPalPlugin\Generator\PayPalAuthAssertionGeneratorInterface;
use Webmozart\Assert\Assert;

final class PayPalPaymentRefundProcessor implements PaymentRefundProcessorInterface
{
    /** @var CacheAuthorizeClientApiInterface */
    private $authorizeClientApi;

    /** @var OrderDetailsApiInterface */
    private $orderDetailsApi;

    /** @var RefundPaymentApiInterface */
    private $refundOrderApi;

    /** @var PayPalAuthAssertionGeneratorInterface */
    private $payPalAuthAssertionGenerator;

    public function __construct(
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        OrderDetailsApiInterface $orderDetailsApi,
        RefundPaymentApiInterface $refundOrderApi,
        PayPalAuthAssertionGeneratorInterface $payPalAuthAssertionGenerator
    ) {
        $this->authorizeClientApi = $authorizeClientApi;
        $this->orderDetailsApi = $orderDetailsApi;
        $this->refundOrderApi = $refundOrderApi;
        $this->payPalAuthAssertionGenerator = $payPalAuthAssertionGenerator;
    }

    public function refund(PaymentInterface $payment): void
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        if ($gatewayConfig->getFactoryName() !== 'sylius.pay_pal') {
            return;
        }

        $details = $payment->getDetails();
        if (!isset($details['paypal_order_id'])) {
            return;
        }

        try {
            $token = $this->authorizeClientApi->authorize($paymentMethod);
            $details = $this->orderDetailsApi->get($token, (string) $details['paypal_order_id']);
            $authAssertion = $this->payPalAuthAssertionGenerator->generate($paymentMethod);
            $payPalPaymentId = (string) $details['purchase_units'][0]['payments']['captures'][0]['id'];

            $response = $this->refundOrderApi->refund($token, $payPalPaymentId, $authAssertion);

            Assert::same($response['status'], 'COMPLETED');
        } catch (ClientException | \InvalidArgumentException $exception) {
            throw new PayPalOrderRefundException();
        }
    }
}
