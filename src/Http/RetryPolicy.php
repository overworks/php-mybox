<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Http;

use Minhyung\Mybox\Exception\InvalidArgumentException;

/**
 * Decides whether a failed attempt is worth repeating and how long to wait.
 *
 * Only statuses that a later identical request could plausibly succeed on are
 * retried. 500 is deliberately excluded: MYBOX returns it for genuine
 * server-side faults rather than transient congestion, so repeating the call
 * mostly burns quota. Non-idempotent verbs are still retried for these
 * statuses because none of them indicate the request was applied.
 */
final class RetryPolicy
{
    public const DEFAULT_RETRYABLE_STATUSES = [429, 502, 503];

    /**
     * @param int        $maxAttempts       Total attempts including the first one. 1 disables retrying.
     * @param float      $baseDelaySeconds  Delay before the first retry; doubles each time.
     * @param float      $maxDelaySeconds   Ceiling for a single wait, before jitter.
     * @param list<int>  $retryableStatuses HTTP statuses worth repeating.
     * @param bool       $jitter            Spread retries randomly to avoid synchronised bursts.
     */
    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly float $baseDelaySeconds = 0.5,
        public readonly float $maxDelaySeconds = 8.0,
        public readonly array $retryableStatuses = self::DEFAULT_RETRYABLE_STATUSES,
        public readonly bool $jitter = true,
    ) {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be at least 1.');
        }

        if ($baseDelaySeconds < 0 || $maxDelaySeconds < 0) {
            throw new InvalidArgumentException('Retry delays cannot be negative.');
        }
    }

    /**
     * A policy that never retries — useful in tests and for callers who want
     * full control over backoff.
     */
    public static function none(): self
    {
        return new self(maxAttempts: 1);
    }

    /**
     * @param int $attempt 1 for the first try.
     */
    public function shouldRetry(int $attempt, int $status): bool
    {
        return $attempt < $this->maxAttempts && in_array($status, $this->retryableStatuses, true);
    }

    /**
     * Whether a transport-level failure (connection reset, timeout) on this
     * attempt should be repeated.
     */
    public function shouldRetryTransportFailure(int $attempt): bool
    {
        return $attempt < $this->maxAttempts;
    }

    /**
     * Seconds to sleep before the next attempt.
     *
     * A `Retry-After` value from the server always wins over the computed
     * backoff, since the server knows when the quota window resets.
     *
     * @param int      $attempt    1 for the first try.
     * @param int|null $retryAfter Seconds requested by the server, if any.
     */
    public function delayFor(int $attempt, ?int $retryAfter = null): float
    {
        if ($retryAfter !== null && $retryAfter >= 0) {
            return (float) $retryAfter;
        }

        $delay = min($this->baseDelaySeconds * (2 ** ($attempt - 1)), $this->maxDelaySeconds);

        if ($this->jitter && $delay > 0) {
            // Full jitter: a uniform draw from [0, delay] decorrelates concurrent clients.
            $delay = $delay * (random_int(0, PHP_INT_MAX) / PHP_INT_MAX);
        }

        return $delay;
    }
}
