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

use GuzzleHttp\ClientInterface;
use PhpSpec\ObjectBehavior;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\PayPalPlugin\Api\GenericApiInterface;

final class GenericApiSpec extends ObjectBehavior
{
    function let(ClientInterface $client): void
    {
        $this->beConstructedWith($client);
    }

    function it_implements_generic_api_interface(): void
    {
        $this->shouldImplement(GenericApiInterface::class);
    }

    function it_calls_api_by_url(
        ClientInterface $client,
        ResponseInterface $response,
        StreamInterface $body
    ): void {
        $client->request('GET', 'http://url.com/', [
            'headers' => [
                'Authorization' => 'Bearer TOKEN',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ])->willReturn($response);

        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{ "parameter": "VALUE" }');

        $this->get('TOKEN', 'http://url.com/')->shouldReturn(['parameter' => 'VALUE']);
    }
}
