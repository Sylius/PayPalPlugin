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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UpdatePayPalOrderAction
{
    public function __construct(
        private readonly PaymentProviderInterface $paymentProvider,
        private readonly CacheAuthorizeClientApiInterface $authorizeClientApi,
        private readonly UpdateOrderApiInterface $updateOrderApi,
        private readonly AddressFactoryInterface $addressFactory,
        private readonly OrderProcessorInterface $orderProcessor,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $payment = $this->paymentProvider->getByPayPalOrderId((string) $request->request->get('orderID'));
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $token = $this->authorizeClientApi->authorize($paymentMethod);

        $shippingAddress = $request->request->all('shipping_address');

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
            (string) $request->request->get('orderID'),
            $payment,
            $payment->getDetails()['reference_id'],
            $gatewayConfig->getConfig()['merchant_id'],
        );

        return new JsonResponse($response);
    }
}
