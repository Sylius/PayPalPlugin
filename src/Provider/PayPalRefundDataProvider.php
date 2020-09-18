<?php

declare(strict_types=1);


namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApi;
use Sylius\PayPalPlugin\Api\RefundDataApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class PayPalRefundDataProvider implements PayPalRefundDataProviderInterface
{
    /** @var CacheAuthorizeClientApi */
    private $authorizeClientApi;

    /** @var RefundDataApiInterface */
    private $refundDataApi;

    /** @var  */
    private $paymentMethodRepository;

    public function __construct(
        CacheAuthorizeClientApi $authorizeClientApi,
        RefundDataApiInterface $refundDataApi,
        PaymentMethodRepositoryInterface $paymentMethodRepository
    ) {
        $this->authorizeClientApi = $authorizeClientApi;
        $this->refundDataApi = $refundDataApi;
    }

    public function provide(string $refundId): array
    {
        $paymentMethod = $this->paymentMethodRepository->
        $token = $this->authorizeClientApi->authorize()
    }
}
