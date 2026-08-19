<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Exception;

/**
 * Base class for every error response returned by the MYBOX API.
 *
 * MYBOX answers failures with a uniform JSON body:
 * `{"code": "PLAT-400", "message": "BAD_REQUEST", "requestId": "...", "timestamp": "..."}`
 *
 * Catch a subclass ({@see NotFoundException}, {@see RateLimitException}, ...) to
 * react to a specific status, or this class to handle any API-reported failure.
 */
class ApiException extends \RuntimeException implements MyboxException
{
    /**
     * @param int         $status       HTTP status code of the response.
     * @param string|null $errorCode    MYBOX error code, e.g. `PLAT-404`.
     * @param string|null $errorMessage Symbolic message, e.g. `NOT_FOUND`.
     * @param string|null $requestId    Correlation id — quote it when contacting support.
     * @param string|null $timestamp    Server-side timestamp of the failure.
     * @param string|null $rawBody      Response body as received, for diagnostics.
     */
    public function __construct(
        public readonly int $status,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $requestId = null,
        public readonly ?string $timestamp = null,
        public readonly ?string $rawBody = null,
    ) {
        parent::__construct(self::buildMessage($status, $errorCode, $errorMessage, $requestId), $status);
    }

    /**
     * Builds the concrete exception subclass matching an HTTP status code.
     *
     * @param array<string, mixed> $body       Decoded error body, may be empty.
     * @param int|null             $retryAfter Parsed `Retry-After` header, in seconds.
     */
    public static function fromResponse(
        int $status,
        array $body,
        ?string $rawBody = null,
        ?int $retryAfter = null,
    ): self {
        $class = match (true) {
            $status === 400 => BadRequestException::class,
            $status === 401 => UnauthorizedException::class,
            $status === 403 => ForbiddenException::class,
            $status === 404 => NotFoundException::class,
            $status === 409 => ConflictException::class,
            $status === 422 => UnprocessableEntityException::class,
            $status === 423 => LockedException::class,
            $status === 429 => RateLimitException::class,
            $status === 507 => InsufficientStorageException::class,
            $status >= 500 => ServerException::class,
            default => self::class,
        };

        if ($class === RateLimitException::class) {
            return new RateLimitException(
                status: $status,
                errorCode: self::stringOrNull($body['code'] ?? null),
                errorMessage: self::stringOrNull($body['message'] ?? null),
                requestId: self::stringOrNull($body['requestId'] ?? null),
                timestamp: self::stringOrNull($body['timestamp'] ?? null),
                rawBody: $rawBody,
                retryAfter: $retryAfter,
            );
        }

        return new $class(
            status: $status,
            errorCode: self::stringOrNull($body['code'] ?? null),
            errorMessage: self::stringOrNull($body['message'] ?? null),
            requestId: self::stringOrNull($body['requestId'] ?? null),
            timestamp: self::stringOrNull($body['timestamp'] ?? null),
            rawBody: $rawBody,
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private static function buildMessage(int $status, ?string $code, ?string $message, ?string $requestId): string
    {
        $text = sprintf('MYBOX API error (HTTP %d)', $status);

        if ($code !== null || $message !== null) {
            $text .= ': ' . trim(($code ?? '') . ' ' . ($message ?? ''));
        }

        if ($requestId !== null) {
            $text .= sprintf(' [requestId: %s]', $requestId);
        }

        return $text;
    }
}
