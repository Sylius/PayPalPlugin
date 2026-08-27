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
use Psr\Http\Message\StreamFactoryInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Symfony\Component\HttpFoundation\Request;

final readonly class OnboardingTokenApi implements OnboardingTokenApiInterface
{
    public function __construct(
        private PayPalOnboardingRequestExecutorInterface $requestExecutor,
        private string $baseUrl,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function getFromAuthorizationCode(string $sharedId, string $authCode, string $sellerNonce): string
    {
        $request = $this->requestFactory->createRequest(Request::METHOD_POST, $this->baseUrl . 'v1/oauth2/token')
            ->withHeader('Authorization', 'Basic ' . base64_encode($sharedId . ':'))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
        ;

        $request = $request->withBody(
            $this->streamFactory->createStream(
                http_build_query(
                    data: [
                        'grant_type' => 'authorization_code',
                        'code' => $authCode,
                        'code_verifier' => $sellerNonce,
                    ],
                    arg_separator: '&',
                ),
            ),
        );

        $content = $this->requestExecutor->execute($request, 'Onboarding token');
        if (!isset($content['access_token'])) {
            throw new PayPalPluginException('Missing access_token');
        }

        return (string) $content['access_token'];
    }
}
