<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Exception;

/**
 * A per-minute or per-day call quota was exhausted (HTTP 429).
 *
 * Quotas depend on the account's MYBOX plan — see the rate-limit table in the
 * README. The default retry policy already backs off and retries a few times
 * before this surfaces, so seeing it means the limit is sustained rather than
 * momentary.
 */
final class RateLimitException extends ApiException
{
    /**
     * @param int|null $retryAfter Seconds the server asked the client to wait,
     *                             taken from the `Retry-After` response header.
     *                             Null when the header was absent.
     */
    public function __construct(
        int $status,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?string $requestId = null,
        ?string $timestamp = null,
        ?string $rawBody = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($status, $errorCode, $errorMessage, $requestId, $timestamp, $rawBody);
    }
}
