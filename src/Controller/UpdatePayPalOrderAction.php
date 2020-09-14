<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UpdatePayPalOrderAction
{
    /** @var OrderProviderInterface */
    private $orderProvider;

    /** @var CacheAuthorizeClientApiInterface */
    private $authorizeClientApi;

    /** @var OrderDetailsApiInterface */
    private $orderDetailsApi;

    /** @var UpdateOrderApiInterface */
    private $updateOrderApi;

    /** @var AddressFactoryInterface */
    private $addressFactory;

    /** @var OrderProcessorInterface */
    private $orderProcessor;

    /** @var PayPalItemDataProviderInterface */
    private $payPalItemDataProvider;

    public function __construct(
        OrderProviderInterface $orderProvider,
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        OrderDetailsApiInterface $orderDetailsApi,
        UpdateOrderApiInterface $updateOrderApi,
        AddressFactoryInterface $addressFactory,
        OrderProcessorInterface $orderProcessor,
        PayPalItemDataProviderInterface $payPalItemDataProvider
    ) {
        $this->orderProvider = $orderProvider;
        $this->authorizeClientApi = $authorizeClientApi;
        $this->orderDetailsApi = $orderDetailsApi;
        $this->updateOrderApi = $updateOrderApi;
        $this->addressFactory = $addressFactory;
        $this->orderProcessor = $orderProcessor;
        $this->payPalItemDataProvider = $payPalItemDataProvider;
    }

    public function __invoke(Request $request): Response
    {
        $order = $this->orderProvider->provideOrderById($request->attributes->getInt('id'));

        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $token = $this->authorizeClientApi->authorize($paymentMethod);

        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();
        $address->setFirstName('Temp');
        $address->setLastName('Temp');
        $address->setStreet('Temp');
        $address->setCity($request->request->get('shipping_address')['city']);
        $address->setPostcode($request->request->get('shipping_address')['postal_code']);
        $address->setCountryCode($request->request->get('shipping_address')['country_code']);
        $order->setBillingAddress($address);

        $this->orderProcessor->process($order);
        $payPalItemData = $this->payPalItemDataProvider->provide($order);

        $this->updateOrderApi->updatePayPalItemData(
            $token,
            $request->request->get('orderID'),
            $payment->getDetails()['reference_id'],
            $payPalItemData
        );

        return new Response();
    }
}
