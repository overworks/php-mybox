<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Http;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Minhyung\Mybox\ClientConfig;
use Minhyung\Mybox\Exception\ApiException;
use Minhyung\Mybox\Exception\TransportException;
use Minhyung\Mybox\Support\Json;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * The single place where this SDK talks HTTP.
 *
 * Responsibilities: building authenticated requests against the MYBOX base
 * URI, encoding and decoding JSON, retrying transient failures, and turning
 * error responses into typed exceptions. Everything above this class deals in
 * arrays and models only.
 */
final class Transport
{
    private readonly ClientInterface $httpClient;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;
    private readonly Sleeper $sleeper;

    /**
     * Any argument left null is resolved through php-http/discovery, so an
     * application that already has Guzzle, Symfony HttpClient, or any other
     * PSR-18 implementation installed needs no wiring at all.
     */
    public function __construct(
        private readonly ClientConfig $config,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?Sleeper $sleeper = null,
    ) {
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->sleeper = $sleeper ?? new SystemSleeper();
    }

    public function config(): ClientConfig
    {
        return $this->config;
    }

    public function httpClient(): ClientInterface
    {
        return $this->httpClient;
    }

    public function requestFactory(): RequestFactoryInterface
    {
        return $this->requestFactory;
    }

    public function streamFactory(): StreamFactoryInterface
    {
        return $this->streamFactory;
    }

    /**
     * @param  array<string, scalar|null> $query
     * @return array<string, mixed>|null  Decoded body, or null for 204 and other empty responses.
     */
    public function get(string $path, array $query = []): ?array
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * @param  array<string, mixed>|null $body
     * @return array<string, mixed>|null
     */
    public function post(string $path, ?array $body = null): ?array
    {
        return $this->request('POST', $path, [], $body);
    }

    /**
     * @param  array<string, mixed>      $body
     * @return array<string, mixed>|null
     */
    public function patch(string $path, array $body): ?array
    {
        return $this->request('PATCH', $path, [], $body);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function delete(string $path): ?array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * Performs an authenticated JSON call against the MYBOX API.
     *
     * @param  array<string, scalar|null> $query Null values are dropped.
     * @param  array<string, mixed>|null  $body  Encoded as a JSON request body.
     * @return array<string, mixed>|null
     *
     * @throws ApiException       When MYBOX reports an error.
     * @throws TransportException When no usable response could be obtained.
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null): ?array
    {
        $request = $this->requestFactory
            ->createRequest($method, $this->buildUri($path, $query))
            ->withHeader('Authorization', $this->config->authorizationHeader())
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->config->resolvedUserAgent());

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($this->encodeJson($body)));
        }

        $response = $this->send($request);
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();

        if ($status >= 400) {
            throw ApiException::fromResponse($status, $this->decodeJson($raw, lenient: true), $raw, self::retryAfter($response));
        }

        if ($status === 204 || trim($raw) === '') {
            return null;
        }

        return $this->decodeJson($raw);
    }

    /**
     * Sends a fully-formed request, repeating it while the retry policy says
     * the failure is transient.
     *
     * The status is not inspected beyond that — callers decide what a given
     * status means. Used directly by the transfer layer, which talks to the
     * storage domain rather than to the API.
     *
     * @throws TransportException When every attempt failed at the network level.
     */
    public function send(RequestInterface $request): ResponseInterface
    {
        $policy = $this->config->retryPolicy;
        $body = $request->getBody();

        // A resumed upload hands us a stream already positioned at the resume
        // offset, so a retry has to return to *that* point rather than to the
        // start of the file. A body we cannot reposition must not be replayed
        // at all — the client may already have drained it.
        $replayable = $body->getSize() === 0 || $body->isSeekable();
        $startOffset = $body->isSeekable() ? $body->tell() : 0;
        $attempt = 1;

        while (true) {
            try {
                $response = $this->httpClient->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                if (!$replayable || !$policy->shouldRetryTransportFailure($attempt)) {
                    throw new TransportException(
                        sprintf('Request to %s failed: %s', $request->getUri(), $e->getMessage()),
                        0,
                        $e,
                    );
                }

                $this->sleeper->sleep($policy->delayFor($attempt));
                self::seekTo($body, $startOffset);
                ++$attempt;

                continue;
            }

            if (!$replayable || !$policy->shouldRetry($attempt, $response->getStatusCode())) {
                return $response;
            }

            $this->sleeper->sleep($policy->delayFor($attempt, self::retryAfter($response)));
            self::seekTo($body, $startOffset);
            ++$attempt;
        }
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private function buildUri(string $path, array $query = []): string
    {
        $uri = $this->config->normalizedBaseUri() . '/' . ltrim($path, '/');
        $params = [];

        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }

            $params[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $params === [] ? $uri : $uri . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function encodeJson(array $body): string
    {
        try {
            return Json::encode($body);
        } catch (\JsonException $e) {
            throw new TransportException('Failed to encode the request body as JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  bool                 $lenient Return an empty array instead of throwing, used for error
     *                                       bodies which may legitimately be empty or non-JSON.
     * @return array<string, mixed>
     */
    private function decodeJson(string $raw, bool $lenient = false): array
    {
        $decoded = Json::decodeObject($raw);

        if ($decoded === null) {
            if ($lenient) {
                return [];
            }

            throw new TransportException('MYBOX returned a body that is not a JSON object.');
        }

        return $decoded;
    }

    /**
     * Repositions a request body so the same payload can be sent again.
     */
    private static function seekTo(StreamInterface $body, int $offset): void
    {
        if ($body->isSeekable()) {
            $body->seek($offset);
        }
    }

    /**
     * Reads `Retry-After` in its delay-seconds form; the HTTP-date form is
     * converted to a delay relative to now.
     */
    private static function retryAfter(ResponseInterface $response): ?int
    {
        $header = $response->getHeaderLine('Retry-After');

        if ($header === '') {
            return null;
        }

        if (ctype_digit(trim($header))) {
            return (int) trim($header);
        }

        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC7231, trim($header));

        return $date === false ? null : max(0, $date->getTimestamp() - time());
    }
}
