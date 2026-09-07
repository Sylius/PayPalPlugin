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

namespace Sylius\PayPalPlugin\Controller;

use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class UpdatePayPalOrderAction
{
    /** @param AddressFactoryInterface<AddressInterface> $addressFactory */
    public function __construct(
        private ?PaymentProviderInterface $paymentProvider,
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private UpdateOrderApiInterface $updateOrderApi,
        private AddressFactoryInterface $addressFactory,
        private OrderProcessorInterface $orderProcessor,
        private ?PaypalPaymentQueryInterface $paypalPaymentQuery = null,
    ) {
        if (null !== $this->paymentProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.7',
                sprintf(
                    'Passing an instance of "%s" as the first argument is deprecated and will be prohibited in 3.0',
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
        $payload = $request->getPayload();
        $orderId = $payload->getString('orderID');

        if (null !== $this->paypalPaymentQuery) {
            $payment = $this->paypalPaymentQuery->getForUpdateByOrderId($orderId);
        } else {
            $payment = $this->paymentProvider->getByPayPalOrderId($orderId);
        }

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $token = $this->authorizeClientApi->authorize($paymentMethod);

        $shippingAddress = $payload->all('shipping_address');

        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();
        $address->setFirstName('Temp');
        $address->setLastName('Temp');
        $address->setStreet('Temp');
        $address->setCity((string) $shippingAddress['city']);
        $address->setPostcode((string) $shippingAddress['postal_code']);
        $address->setCountryCode((string) $shippingAddress['country_code']);
        $order->setBillingAddress($address);
        $order->setShippingAddress($address);

        $this->orderProcessor->process($order);

        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        $response = $this->updateOrderApi->update(
            $token,
            $orderId,
            $payment,
            $payment->getDetails()['reference_id'],
            $gatewayConfig->getConfig()['merchant_id'],
        );

        return new JsonResponse($response);
    }
}
