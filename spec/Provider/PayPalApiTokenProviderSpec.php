<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Provider;

use PhpSpec\ObjectBehavior;
use Sylius\PayPalPlugin\Provider\ApiTokenProviderInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class PayPalApiTokenProviderSpec extends ObjectBehavior
{
    function let(HttpClientInterface $httpClient): void
    {
        $this->beConstructedWith($httpClient, 'client_id', 'SECRET');
    }

    function it_implements_api_token_provider_interface(): void
    {
        $this->shouldImplement(ApiTokenProviderInterface::class);
    }

    function it_provides_bearer_token_for_configured_client_id_and_secret(
        HttpClientInterface $httpClient,
        ResponseInterface $response
    ): void {
        $httpClient->request('POST', 'https://api.sandbox.paypal.com/v1/oauth2/token', [
            'auth_basic' => ['client_id', 'SECRET'],
            'body' => ['grant_type' => 'client_credentials'],
        ])->willReturn($response);

        $response->getStatusCode()->willReturn(200);
        $response->toArray()->willReturn([
            'token_type' => 'Bearer',
            'access_token' => '123123IAMTOKEN!@#!@#',
        ]);

        $this->getToken()->shouldReturn('123123IAMTOKEN!@#!@#');
    }

    function it_throws_an_exception_if_token_cannot_be_provided(
        HttpClientInterface $httpClient,
        ResponseInterface $response
    ): void {
        $httpClient->request('POST', 'https://api.sandbox.paypal.com/v1/oauth2/token', [
            'auth_basic' => ['client_id', 'SECRET'],
            'body' => ['grant_type' => 'client_credentials'],
        ])->willReturn($response);

        $response->getStatusCode()->willReturn(500);

        $this
            ->shouldThrow(HttpException::class)
            ->during('getToken', [])
        ;
    }
}
