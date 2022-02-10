<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\GenericApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;

final class PayPalWebhookDataProvider implements PayPalWebhookDataProviderInterface
{
    private CacheAuthorizeClientApiInterface $authorizeClientApi;
    private PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider;
    private GenericApiInterface $genericApi;

    public function __construct(
        CacheAuthorizeClientApiInterface     $authorizeClientApi,
        GenericApiInterface                  $genericApi,
        PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider
    )
    {
        $this->authorizeClientApi = $authorizeClientApi;
        $this->genericApi = $genericApi;
        $this->payPalPaymentMethodProvider = $payPalPaymentMethodProvider;
    }

    public function provide(string $url, string $rel): array
    {
        $paymentMethod = $this->payPalPaymentMethodProvider->provide();
        $token = $this->authorizeClientApi->authorize($paymentMethod);

        $webhookData = $this->genericApi->get($token, $url);

        /** @var string[] $link */
        foreach ($webhookData['links'] as $link) {
            if ($link['rel'] === $rel) {
                return $this->genericApi->get($token, $link['href']);
            }
        }

        throw new PayPalWrongDataException();
    }
}
