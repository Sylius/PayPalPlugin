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

use PhpSpec\ObjectBehavior;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\PayPalPlugin\Api\GenericApiInterface;

final class GenericApiSpec extends ObjectBehavior
{
    function let(ClientInterface $client, RequestFactoryInterface $requestFactory): void
    {
        $this->beConstructedWith($client, $requestFactory);
    }

    function it_implements_generic_api_interface(): void
    {
        $this->shouldImplement(GenericApiInterface::class);
    }

    function it_calls_api_by_url(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        RequestInterface $request,
        ResponseInterface $response,
        StreamInterface $body
    ): void {

        $requestFactory->createRequest('GET', 'http://url.com/')->willReturn($request);

        $request->withHeader('Authorization', 'Bearer TOKEN')->willReturn($request);
        $request->withHeader('Content-Type', 'application/json')->willReturn($request);
        $request->withHeader('Accept', 'application/json')->willReturn($request);

        $client->sendRequest($request)->willReturn($response);
        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{ "parameter": "VALUE" }');

        $this->get('TOKEN', 'http://url.com/')->shouldReturn(['parameter' => 'VALUE']);
    }
}
