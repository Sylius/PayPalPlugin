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

namespace Tests\Sylius\PayPalPlugin\Unit\Exception;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiAuthenticationException;
use Sylius\PayPalPlugin\Exception\PayPalApiConnectionException;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Exception\PayPalApiNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Exception\PayPalApiServerException;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalNetworkFailure;

final class PayPalApiExceptionTest extends TestCase
{
    /** @param class-string<PayPalApiException> $exceptionClass */
    #[DataProvider('statusCodes')]
    public function test_it_tells_the_kind_of_failure_by_the_status_code(int $statusCode, string $exceptionClass): void
    {
        self::assertInstanceOf($exceptionClass, PayPalApiException::fromResponse('GET', 'v2/checkout/orders/ORDER_ID', $this->response($statusCode, '{}')));
    }

    /** @return iterable<string, array{int, class-string<PayPalApiException>}> */
    public static function statusCodes(): iterable
    {
        yield 'bad request' => [400, PayPalApiRequestException::class];
        yield 'unauthorized' => [401, PayPalApiAuthenticationException::class];
        yield 'forbidden' => [403, PayPalApiRequestException::class];
        yield 'not found' => [404, PayPalApiNotFoundException::class];
        yield 'unprocessable entity' => [422, PayPalApiRequestException::class];
        yield 'too many requests' => [429, PayPalApiServerException::class];
        yield 'internal server error' => [500, PayPalApiServerException::class];
        yield 'service unavailable' => [503, PayPalApiServerException::class];
    }

    public function test_it_carries_the_paypal_error_with_its_issues(): void
    {
        $exception = PayPalApiException::fromResponse('POST', 'v2/checkout/orders/ORDER_ID/capture', $this->response(422, (string) json_encode([
            'name' => 'UNPROCESSABLE_ENTITY',
            'message' => 'The requested action could not be performed.',
            'details' => [['issue' => 'INSTRUMENT_DECLINED', 'description' => 'The instrument was declined.'], ['field' => '/amount']],
            'debug_id' => 'BODY_DEBUG_ID',
        ]), ['PayPal-Debug-Id' => 'HEADER_DEBUG_ID']));

        self::assertSame('POST', $exception->getMethod());
        self::assertSame('v2/checkout/orders/ORDER_ID/capture', $exception->getPath());
        self::assertSame(422, $exception->getStatusCode());
        self::assertSame('UNPROCESSABLE_ENTITY', $exception->getErrorName());
        self::assertSame('The requested action could not be performed.', $exception->getErrorMessage());
        self::assertSame(['INSTRUMENT_DECLINED'], $exception->getIssues());
        self::assertSame(['The instrument was declined.'], $exception->getIssueDescriptions());
        self::assertTrue($exception->hasIssue('INSTRUMENT_DECLINED'));
        self::assertFalse($exception->hasIssue('PAYER_ACTION_REQUIRED'));
        self::assertSame('HEADER_DEBUG_ID', $exception->getDebugId());
        self::assertSame(
            'PayPal rejected the "POST v2/checkout/orders/ORDER_ID/capture" request with HTTP 422: UNPROCESSABLE_ENTITY The instrument was declined. [INSTRUMENT_DECLINED] (debug id: HEADER_DEBUG_ID)',
            $exception->getMessage(),
        );
    }

    public function test_it_describes_the_error_with_its_message_when_paypal_gives_no_issue_description(): void
    {
        $exception = PayPalApiException::fromResponse('POST', 'v2/payments/captures/CAPTURE_ID/refund', $this->response(422, (string) json_encode([
            'name' => 'UNPROCESSABLE_ENTITY',
            'message' => 'The requested action could not be performed.',
            'details' => [['issue' => 'REFUND_AMOUNT_EXCEEDED']],
        ])));

        self::assertSame([], $exception->getIssueDescriptions());
        self::assertSame('UNPROCESSABLE_ENTITY The requested action could not be performed. [REFUND_AMOUNT_EXCEEDED]', $exception->getDescription());
    }

    public function test_it_takes_the_debug_id_from_the_body_when_the_header_is_missing(): void
    {
        $exception = PayPalApiException::fromResponse('GET', 'v2/checkout/orders/ORDER_ID', $this->response(404, '{"name": "RESOURCE_NOT_FOUND", "debug_id": "BODY_DEBUG_ID"}'));

        self::assertSame('BODY_DEBUG_ID', $exception->getDebugId());
    }

    public function test_it_reads_an_oauth_error(): void
    {
        $exception = PayPalApiException::fromResponse('POST', 'v1/oauth2/token', $this->response(401, '{"error": "invalid_client", "error_description": "Client Authentication failed"}'));

        self::assertInstanceOf(PayPalApiAuthenticationException::class, $exception);
        self::assertSame('invalid_client', $exception->getErrorName());
        self::assertSame('Client Authentication failed', $exception->getErrorMessage());
    }

    public function test_it_describes_a_response_paypal_sent_no_error_with(): void
    {
        $exception = PayPalApiException::fromResponse('GET', 'v2/checkout/orders/ORDER_ID', $this->response(502, '<html>Bad Gateway</html>'));

        self::assertNull($exception->getErrorName());
        self::assertSame([], $exception->getIssues());
        self::assertNull($exception->getDebugId());
        self::assertSame('PayPal rejected the "GET v2/checkout/orders/ORDER_ID" request with HTTP 502: unknown error', $exception->getMessage());
    }

    public function test_it_reads_a_response_body_another_reader_has_already_consumed(): void
    {
        $response = $this->response(404, '{"name": "RESOURCE_NOT_FOUND"}');
        $response->getBody()->getContents();

        self::assertSame('RESOURCE_NOT_FOUND', PayPalApiException::fromResponse('GET', 'v2/checkout/orders/ORDER_ID', $response)->getErrorName());
    }

    public function test_it_tells_that_paypal_could_not_be_reached(): void
    {
        $failure = new PayPalNetworkFailure('Connection timed out', (new Psr17Factory())->createRequest('GET', 'https://api.paypal.com/v2/checkout/orders/ORDER_ID'));

        $exception = PayPalApiException::fromClientFailure('GET', 'v2/checkout/orders/ORDER_ID', $failure);

        self::assertInstanceOf(PayPalApiConnectionException::class, $exception);
        self::assertNull($exception->getStatusCode());
        self::assertSame($failure, $exception->getPrevious());
        self::assertSame('PayPal could not be reached for the "GET v2/checkout/orders/ORDER_ID" request: Connection timed out', $exception->getMessage());
    }

    /** @param array<string, string> $headers */
    private function response(int $statusCode, string $body, array $headers = []): ResponseInterface
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse($statusCode)->withBody($factory->createStream($body));

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
