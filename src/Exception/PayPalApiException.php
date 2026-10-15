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

namespace Sylius\PayPalPlugin\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

abstract class PayPalApiException extends \RuntimeException
{
    /** @param list<string> $issues */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly ?int $statusCode = null,
        private readonly ?string $errorName = null,
        private readonly ?string $errorMessage = null,
        private readonly array $issues = [],
        private readonly ?string $debugId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            null === $statusCode
                ? sprintf('PayPal could not be reached for the "%s %s" request: %s', $method, $path, $previous?->getMessage() ?? 'unknown failure')
                : sprintf('PayPal rejected the "%s %s" request with HTTP %d: %s', $method, $path, $statusCode, $this->getDescription()),
            0,
            $previous,
        );
    }

    public static function fromResponse(string $method, string $path, ResponseInterface $response): self
    {
        $statusCode = $response->getStatusCode();
        $content = json_decode((string) $response->getBody(), true);
        $content = is_array($content) ? $content : [];

        $issues = [];
        foreach (is_array($content['details'] ?? null) ? $content['details'] : [] as $detail) {
            if (is_array($detail) && is_string($detail['issue'] ?? null)) {
                $issues[] = $detail['issue'];
            }
        }

        $debugId = $response->getHeaderLine('PayPal-Debug-Id');
        if ('' === $debugId) {
            $debugId = is_string($content['debug_id'] ?? null) ? $content['debug_id'] : null;
        }

        $arguments = [
            $method,
            $path,
            $statusCode,
            self::stringOrNull($content['name'] ?? $content['error'] ?? null),
            self::stringOrNull($content['message'] ?? $content['error_description'] ?? null),
            $issues,
            $debugId,
        ];

        return match (true) {
            Response::HTTP_UNAUTHORIZED === $statusCode => new PayPalApiAuthenticationException(...$arguments),
            Response::HTTP_NOT_FOUND === $statusCode => new PayPalApiNotFoundException(...$arguments),
            Response::HTTP_TOO_MANY_REQUESTS === $statusCode, $statusCode >= Response::HTTP_INTERNAL_SERVER_ERROR => new PayPalApiServerException(...$arguments),
            default => new PayPalApiRequestException(...$arguments),
        };
    }

    public static function fromClientFailure(string $method, string $path, ClientExceptionInterface $failure): PayPalApiConnectionException
    {
        return new PayPalApiConnectionException($method, $path, previous: $failure);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getErrorName(): ?string
    {
        return $this->errorName;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /** @return list<string> */
    public function getIssues(): array
    {
        return $this->issues;
    }

    public function hasIssue(string $issue): bool
    {
        return in_array($issue, $this->issues, true);
    }

    public function getDebugId(): ?string
    {
        return $this->debugId;
    }

    public function getDescription(): string
    {
        $parts = [$this->errorName ?? 'unknown error'];

        if (null !== $this->errorMessage) {
            $parts[] = $this->errorMessage;
        }

        if ([] !== $this->issues) {
            $parts[] = sprintf('[%s]', implode(', ', $this->issues));
        }

        if (null !== $this->debugId) {
            $parts[] = sprintf('(debug id: %s)', $this->debugId);
        }

        return implode(' ', $parts);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && '' !== $value ? $value : null;
    }
}
