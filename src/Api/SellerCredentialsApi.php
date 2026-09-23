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
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Symfony\Component\HttpFoundation\Request;

final readonly class SellerCredentialsApi implements SellerCredentialsApiInterface
{
    public function __construct(
        private PayPalOnboardingRequestExecutorInterface $requestExecutor,
        private string $baseUrl,
        private RequestFactoryInterface $requestFactory,
    ) {
    }

    public function get(string $onboardingToken, string $partnerId): array
    {
        $request = $this->requestFactory->createRequest(
            Request::METHOD_GET,
            sprintf('%sv1/customer/partners/%s/merchant-integrations/credentials', $this->baseUrl, $partnerId),
        )
            ->withHeader('Authorization', 'Bearer ' . $onboardingToken)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
        ;

        $content = $this->requestExecutor->execute($request, 'Seller credentials');
        if (!isset($content['client_id'], $content['client_secret'], $content['payer_id'])) {
            throw new PayPalPluginException('client_id/client_secret/payer_id is missing in response');
        }

        return [
            'client_id' => (string) $content['client_id'],
            'client_secret' => (string) $content['client_secret'],
            'payer_id' => (string) $content['payer_id'],
        ];
    }
}
