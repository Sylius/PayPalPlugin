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

namespace spec\Sylius\PayPalPlugin\Api;

use GuzzleHttp\Client;
use PhpSpec\ObjectBehavior;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalAuthorizationException;

final class AuthorizeClientApiSpec extends ObjectBehavior
{
    function let(Client $client): void
    {
        $this->beConstructedWith($client);
    }

    function it_implements_authorize_client_api_interface(): void
    {
        $this->shouldImplement(AuthorizeClientApiInterface::class);
    }

    function it_returns_auth_token_for_given_client_data(
        Client $client,
        ResponseInterface $response,
        StreamInterface $body
    ): void {
        $client->request(
            'POST',
            'https://api.sandbox.paypal.com/v1/oauth2/token',
            [
                'auth' => ['CLIENT_ID', 'CLIENT_SECRET'],
                'form_params' => ['grant_type' => 'client_credentials'],
            ]
        )->willReturn($response);
        $response->getStatusCode()->willReturn(200);
        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{"access_token": "TOKEN"}');

        $this->authorize('CLIENT_ID', 'CLIENT_SECRET')->shouldReturn('TOKEN');
    }

    function it_throws_an_exception_if_client_could_not_be_authorized(
        Client $client,
        ResponseInterface $response
    ): void {
        $client->request(
            'POST',
            'https://api.sandbox.paypal.com/v1/oauth2/token',
            [
                'auth' => ['CLIENT_ID', 'CLIENT_SECRET'],
                'form_params' => ['grant_type' => 'client_credentials'],
            ]
        )->willReturn($response);
        $response->getStatusCode()->willReturn(401);

        $this
            ->shouldThrow(PayPalAuthorizationException::class)
            ->during('authorize', ['CLIENT_ID', 'CLIENT_SECRET'])
        ;
    }
}
