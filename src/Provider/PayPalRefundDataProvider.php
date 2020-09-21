<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\RefundDataApiInterface;
use Sylius\PayPalPlugin\Api\RefundOrderDetailsApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;

final class PayPalRefundDataProvider implements PayPalRefundDataProviderInterface
{
    /** @var CacheAuthorizeClientApiInterface */
    private $authorizeClientApi;

    /** @var PayPalPaymentMethodProviderInterface */
    private $payPalPaymentMethodProvider;

    /** @var RefundDataApiInterface */
    private $refundDataApi;

    /** @var RefundOrderDetailsApiInterface */
    private $refundOrderDetailsApi;

    public function __construct(
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        RefundDataApiInterface $refundDataApi,
        PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
        RefundOrderDetailsApiInterface $refundOrderDetailsApi
    ) {
        $this->authorizeClientApi = $authorizeClientApi;
        $this->refundDataApi = $refundDataApi;
        $this->payPalPaymentMethodProvider = $payPalPaymentMethodProvider;
        $this->refundOrderDetailsApi = $refundOrderDetailsApi;
    }

    public function provide(string $url): array
    {
        $paymentMethod = $this->payPalPaymentMethodProvider->provide();
        $token = $this->authorizeClientApi->authorize($paymentMethod);

        $refundData = $this->refundDataApi->get($token, $url);

        /** @var string[] $link */
        foreach ($refundData['links'] as $link) {
            if ($link['rel'] === 'up') {
                return $this->refundOrderDetailsApi->get($token, $link['href']);
            }
        }

        throw new PayPalWrongDataException();
    }
}
