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

namespace spec\Sylius\PayPalPlugin\Client;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use PhpSpec\ObjectBehavior;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;

final class PayPalClientSpec extends ObjectBehavior
{
    function let(ClientInterface $client, LoggerInterface $logger, UuidProviderInterface $uuidProvider): void
    {
        $this->beConstructedWith($client, $logger, $uuidProvider, 'https://test-api.paypal.com/', 'TRACKING-ID');
    }

    function it_implements_pay_pal_client_interface(): void
    {
        $this->shouldImplement(PayPalClientInterface::class);
    }

    function it_calls_get_request_on_paypal_api(
        ClientInterface $client,
        ResponseInterface $response,
        StreamInterface $body
    ): void {
        $client->request(
            'GET',
            'https://test-api.paypal.com/v2/get-request/',
            [
                'headers' => [
                    'Authorization' => 'Bearer TOKEN',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'PayPal-Partner-Attribution-Id' => 'TRACKING-ID',
                ],
            ]
        )->willReturn($response);
        $response->getStatusCode()->willReturn(200);
        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{"status": "OK", "id": "123123"}');

        $this->get('v2/get-request/', 'TOKEN')->shouldReturn(['status' => 'OK', 'id' => '123123']);
    }

    function it_logs_debug_id_from_failed_get_request(
        ClientInterface $client,
        LoggerInterface $logger,
        RequestException $exception,
        ResponseInterface $response,
        StreamInterface $body
    ): void {
        $client->request(
            'GET',
            'https://test-api.paypal.com/v2/get-request/',
            [
                'headers' => [
                    'Authorization' => 'Bearer TOKEN',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'PayPal-Partner-Attribution-Id' => 'TRACKING-ID',
                ],
            ]
        )->willThrow($exception->getWrappedObject());

        $exception->getResponse()->willReturn($response);
        $response->getBody()->willReturn($body);
        $response->getStatusCode()->willReturn(400);
        $body->getContents()->willReturn('{"status": "FAILED", "debug_id": "123123"}');

        $logger
            ->error('GET request to "https://test-api.paypal.com/v2/get-request/" failed with debug ID 123123')
            ->shouldBeCalled()
        ;

        $this->get('v2/get-request/', 'TOKEN')->shouldReturn(['status' => 'FAILED', 'debug_id' => '123123']);
    }

    function it_calls_post_request_on_paypal_api(
        ClientInterface $client,
        ResponseInterface $response,
        StreamInterface $body,
        UuidProviderInterface $uuidProvider
    ): void {
        $uuidProvider->provide()->willReturn('REQUEST-ID');

        $client->request(
            'POST',
            'https://test-api.paypal.com/v2/post-request/',
            [
                'headers' => [
                    'Authorization' => 'Bearer TOKEN',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'PayPal-Partner-Attribution-Id' => 'TRACKING-ID',
                    'PayPal-Request-Id' => 'REQUEST-ID',
                ],
                'json' => ['parameter' => 'value', 'another_parameter' => 'another_value'],
            ]
        )->willReturn($response);
        $response->getStatusCode()->willReturn(200);
        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{"status": "OK", "id": "123123"}');

        $this
            ->post('v2/post-request/', 'TOKEN', ['parameter' => 'value', 'another_parameter' => 'another_value'])
            ->shouldReturn(['status' => 'OK', 'id' => '123123'])
        ;
    }

    function it_logs_debug_id_from_failed_post_request(
        ClientInterface $client,
        LoggerInterface $logger,
        RequestException $exception,
        ResponseInterface $response,
        StreamInterface $body,
        UuidProviderInterface $uuidProvider
    ): void {
        $uuidProvider->provide()->willReturn('REQUEST-ID');

        $client->request(
            'POST',
            'https://test-api.paypal.com/v2/post-request/',
            [
                'headers' => [
                    'Authorization' => 'Bearer TOKEN',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'PayPal-Partner-Attribution-Id' => 'TRACKING-ID',
                    'PayPal-Request-Id' => 'REQUEST-ID',
                ],
                'json' => ['parameter' => 'value', 'another_parameter' => 'another_value'],
            ]
        )->willThrow($exception->getWrappedObject());

        $exception->getResponse()->willReturn($response);
        $response->getBody()->willReturn($body);
        $response->getStatusCode()->willReturn(400);
        $body->getContents()->willReturn('{"status": "FAILED", "debug_id": "123123"}');

        $logger
            ->error('POST request to "https://test-api.paypal.com/v2/post-request/" failed with debug ID 123123')
            ->shouldBeCalled()
        ;

        $this
            ->post('v2/post-request/', 'TOKEN', ['parameter' => 'value', 'another_parameter' => 'another_value'])
            ->shouldReturn(['status' => 'FAILED', 'debug_id' => '123123'])
        ;
    }

    function it_calls_patch_request_on_paypal_api(
        ClientInterface $client,
        ResponseInterface $response,
        StreamInterface $body
    ): void {
        $client->request(
            'PATCH',
            'https://test-api.paypal.com/v2/patch-request/123123',
            [
                'headers' => [
                    'Authorization' => 'Bearer TOKEN',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'PayPal-Partner-Attribution-Id' => 'TRACKING-ID',
                ],
                'json' => ['parameter' => 'value', 'another_parameter' => 'another_value'],
            ]
        )->willReturn($response);
        $response->getStatusCode()->willReturn(200);
        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{"status": "OK", "id": "123123"}');

        $this
            ->patch('v2/patch-request/123123', 'TOKEN', ['parameter' => 'value', 'another_parameter' => 'another_value'])
            ->shouldReturn(['status' => 'OK', 'id' => '123123'])
        ;
    }

    function it_logs_debug_id_from_failed_patch_request(
        ClientInterface $client,
        LoggerInterface $logger,
        RequestException $exception,
        ResponseInterface $response,
        StreamInterface $body
    ): void {
        $client->request(
            'PATCH',
            'https://test-api.paypal.com/v2/patch-request/123123',
            [
                'headers' => [
                    'Authorization' => 'Bearer TOKEN',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'PayPal-Partner-Attribution-Id' => 'TRACKING-ID',
                ],
                'json' => ['parameter' => 'value', 'another_parameter' => 'another_value'],
            ]
        )->willThrow($exception->getWrappedObject());

        $exception->getResponse()->willReturn($response);
        $response->getBody()->willReturn($body);
        $response->getStatusCode()->willReturn(400);
        $body->getContents()->willReturn('{"status": "FAILED", "debug_id": "123123"}');

        $logger
            ->error('PATCH request to "https://test-api.paypal.com/v2/patch-request/123123" failed with debug ID 123123')
            ->shouldBeCalled()
        ;

        $this
            ->patch('v2/patch-request/123123', 'TOKEN', ['parameter' => 'value', 'another_parameter' => 'another_value'])
            ->shouldReturn(['status' => 'FAILED', 'debug_id' => '123123'])
        ;
    }
}
