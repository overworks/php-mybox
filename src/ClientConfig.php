<?php

declare(strict_types=1);

namespace Minhyung\Mybox;

use Minhyung\Mybox\Exception\InvalidArgumentException;
use Minhyung\Mybox\Http\RetryPolicy;

/**
 * Immutable settings for a {@see MyboxClient}.
 */
final class ClientConfig
{
    public const DEFAULT_BASE_URI = 'https://open-api.mybox.naver.com';

    /**
     * @param string      $accessToken Personal access token issued from
     *                                 MYBOX web → 설정 → 계정 및 개인 액세스 토큰 관리.
     * @param string      $baseUri     Override only for testing against a stub server.
     * @param string|null $userAgent   Appended to the SDK's own identifier.
     */
    public function __construct(
        public readonly string $accessToken,
        public readonly string $baseUri = self::DEFAULT_BASE_URI,
        public readonly RetryPolicy $retryPolicy = new RetryPolicy(),
        public readonly ?string $userAgent = null,
    ) {
        if (trim($accessToken) === '') {
            throw new InvalidArgumentException('A MYBOX personal access token is required.');
        }

        if (filter_var($baseUri, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException(sprintf('Base URI "%s" is not a valid URL.', $baseUri));
        }
    }

    /**
     * Value for the `Authorization` header.
     */
    public function authorizationHeader(): string
    {
        return 'Bearer ' . $this->accessToken;
    }

    /**
     * Base URI without a trailing slash, so paths can be concatenated directly.
     */
    public function normalizedBaseUri(): string
    {
        return rtrim($this->baseUri, '/');
    }

    public function resolvedUserAgent(): string
    {
        $identifier = 'minhyung-mybox-php/' . MyboxClient::VERSION;

        return $this->userAgent === null ? $identifier : $this->userAgent . ' ' . $identifier;
    }

    public function withRetryPolicy(RetryPolicy $policy): self
    {
        return new self($this->accessToken, $this->baseUri, $policy, $this->userAgent);
    }
}
