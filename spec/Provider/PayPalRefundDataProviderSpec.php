<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Provider;

use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\RefundDataApiInterface;
use Sylius\PayPalPlugin\Api\RefundOrderDetailsApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;

final class PayPalRefundDataProviderSpec extends ObjectBehavior
{
    public function let(
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        RefundDataApiInterface $refundDataApi,
        PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
        RefundOrderDetailsApiInterface $refundOrderDetailsApi
    ) {
        $this->beConstructedWith($authorizeClientApi, $refundDataApi, $payPalPaymentMethodProvider, $refundOrderDetailsApi);
    }

    public function it_provides_data_from_provided_url(
        PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
        PaymentMethodInterface $paymentMethod,
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        RefundDataApiInterface $refundDataApi,
        RefundOrderDetailsApiInterface $refundOrderDetailsApi
    ): void {
        $payPalPaymentMethodProvider->provide()->willReturn($paymentMethod);
        $authorizeClientApi->authorize($paymentMethod)->willReturn('TOKEN');
        $refundDataApi->get('TOKEN', 'https://get-refund-data.com')->willReturn(
            [
                'links' => [
                    ['rel' => 'self', 'href' => 'https://self.url.com'],
                    ['rel' => 'up', 'href' => 'https://up.url.com'],
                ],
            ],
        );

        $refundOrderDetailsApi->get('TOKEN', 'https://up.url.com')->shouldBeCalled();

        $this->provide('https://get-refund-data.com');
    }

    public function it_throws_error_if_paypal_data_doesnt_contain_url(
        PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
        PaymentMethodInterface $paymentMethod,
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        RefundDataApiInterface $refundDataApi
    ): void {
        $payPalPaymentMethodProvider->provide()->willReturn($paymentMethod);
        $authorizeClientApi->authorize($paymentMethod)->willReturn('TOKEN');
        $refundDataApi->get('TOKEN', 'https://get-refund-data.com')->willReturn(
            [
                'links' => [
                    ['rel' => 'self', 'href' => 'https://self.url.com'],
                    ['rel' => 'get', 'href' => 'https://get.url.com'],
                ],
            ],
        );

        $this->shouldThrow(PayPalWrongDataException::class)->during('provide', ['https://get-refund-data.com']);
    }
}
