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

namespace Sylius\PayPalPlugin\Api;

use Psr\Http\Message\RequestFactoryInterface;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Symfony\Component\HttpFoundation\Request;

final readonly class MerchantOnboardingStatusApi implements MerchantOnboardingStatusApiInterface
{
    public function __construct(
        private PayPalOnboardingRequestExecutorInterface $requestExecutor,
        private RequestFactoryInterface $requestFactory,
        private string $baseUrl,
    ) {
    }

    public function get(string $sellerToken, string $partnerId, string $merchantId): OnboardingStatus
    {
        $request = $this->requestFactory->createRequest(
            Request::METHOD_GET,
            sprintf('%sv1/customer/partners/%s/merchant-integrations/%s', $this->baseUrl, $partnerId, $merchantId),
        )
            ->withHeader('Authorization', 'Bearer ' . $sellerToken)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
        ;

        $content = $this->requestExecutor->execute($request, 'Onboarding status');

        return new OnboardingStatus(
            (bool) ($content['payments_receivable'] ?? false),
            (bool) ($content['primary_email_confirmed'] ?? false),
        );
    }
}
